<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\File;
use App\Models\MaintenanceRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * Moves the files of repair requests that were uploaded before uploads became private off the public disk, where the web
 * server hands them to anybody holding the address. After it, they are reached only through GET /attachments/{id}, which
 * asks the request's own policy. Deleted requests and deleted attachments count: their bytes are still on the disk.
 *
 * Run once after deploying, `--dry-run` first. Running it again only touches what is still public. An asset's files stay
 * public, and so does a file a request shares with anything else. Nothing is lost on the way: File::moveToPrivate copies
 * the bytes before the public copy goes.
 */
class MakeRequestFilesPrivate extends Command
{
    protected $signature = 'attachments:make-private {--dry-run : say what would move and move nothing}';

    protected $description = 'Move repair request files uploaded before uploads became private to the private disk';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $request = (new MaintenanceRequest)->getMorphClass();

        $moved = 0;
        $bytes = 0;
        $shared = [];
        $missing = [];
        $failed = [];

        File::query()
            ->where('disk', '!=', File::PRIVATE_DISK)
            ->whereHas('attachments', fn ($q) => $q->withTrashed()->where('attachable_type', $request))
            ->chunkById(100, function ($files) use ($dry, $request, &$moved, &$bytes, &$shared, &$missing, &$failed) {
                foreach ($files as $file) {
                    if ($file->attachments()->withTrashed()->where('attachable_type', '!=', $request)->exists()) {
                        $shared[] = $file->id;
                        continue;
                    }

                    if (! Storage::disk($file->disk)->exists($file->path)) {
                        $missing[] = $file->id;
                        continue;
                    }

                    if (! $dry) {
                        try {
                            $file->moveToPrivate();
                        } catch (\Throwable $e) {
                            $failed[$file->id] = $e->getMessage();
                            continue;
                        }
                    }

                    $moved++;
                    $bytes += (int) $file->size;
                }
            });

        // An older upload of the same bytes could point a public attachment at a file already on the private disk.
        $mislabelled = Attachment::withTrashed()
            ->where('attachable_type', $request)
            ->where('is_private', false)
            ->whereHas('file', fn ($q) => $q->where('disk', File::PRIVATE_DISK));
        $marked = (clone $mislabelled)->count();
        if (! $dry && $marked) {
            $mislabelled->update(['is_private' => true]);
        }

        $this->info(sprintf(
            'attachments:make-private %s %d file(s) (%s) to the private disk.',
            $dry ? 'would move' : 'moved',
            $moved,
            Number::fileSize($bytes)
        ));
        if ($marked) {
            $this->info(($dry ? 'Would mark' : 'Marked')." {$marked} attachment(s) private whose file was already on the private disk.");
        }
        if ($shared) {
            $this->warn(count($shared).' file(s) are also attached to something that is not a repair request and stay public: '.implode(', ', $shared));
        }
        if ($missing) {
            $this->warn(count($missing).' file(s) are no longer on the disk and were left as they are: '.implode(', ', $missing));
        }
        foreach ($failed as $id => $message) {
            $this->error("File {$id} was not moved: {$message}");
        }

        if (! $dry) {
            Log::info('[attachments:make-private] done', [
                'moved' => $moved, 'bytes' => $bytes, 'marked' => $marked,
                'shared' => $shared, 'missing' => $missing, 'failed' => array_keys($failed),
            ]);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
