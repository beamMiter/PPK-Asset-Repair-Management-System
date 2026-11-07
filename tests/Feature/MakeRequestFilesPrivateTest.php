<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\File;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Files attached to a repair request before uploads became private (PR #18) were still on the public disk, open to anybody
 * holding their address. `attachments:make-private` moves them, once, after deploying.
 */
class MakeRequestFilesPrivateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    /** A file as an older upload left it: on the public disk and marked public. */
    private function oldPublicFile(object $target, string $bytes, array $attachment = []): Attachment
    {
        $path = 'maintenance/old/'.md5($bytes).'.jpg';
        Storage::disk('public')->put($path, $bytes);
        $file = File::create([
            'path' => $path, 'disk' => 'public', 'mime' => 'image/jpeg', 'size' => strlen($bytes),
            'checksum_sha256' => hash('sha256', $bytes),
        ]);

        return $this->attach($target, $file, $attachment);
    }

    private function attach(object $target, File $file, array $attachment = []): Attachment
    {
        return Attachment::create($attachment + [
            'attachable_type' => $target->getMorphClass(),
            'attachable_id' => $target->getKey(),
            'file_id' => $file->id,
            'original_name' => 'photo.jpg',
            'is_private' => false,
        ]);
    }

    private function job(?User $reporter = null): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create(['reporter_id' => ($reporter ?? User::factory()->create(['role' => 'member']))->id]);
    }

    private function assertMovedToPrivate(Attachment $att, string $bytes): void
    {
        $att->refresh()->load('file');
        $this->assertTrue($att->is_private);
        $this->assertSame(File::PRIVATE_DISK, $att->file->disk);
        $this->assertSame($bytes, Storage::disk('local')->get($att->file->path), 'the bytes arrive whole');
        Storage::disk('public')->assertMissing($att->file->path);
    }

    public function test_it_moves_the_old_files_of_every_request_to_the_private_disk(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);
        $one = $this->oldPublicFile($this->job($reporter), 'first photo');
        $two = $this->oldPublicFile($this->job(), 'second photo');

        $this->artisan('attachments:make-private')->expectsOutputToContain('moved 2 file(s)')->assertSuccessful();

        $this->assertMovedToPrivate($one, 'first photo');
        $this->assertMovedToPrivate($two, 'second photo');

        // and its reporter still opens it, now through the guarded route
        $res = $this->actingAs($reporter)->get(route('attachments.show', $one))->assertOk();
        $this->assertSame('first photo', $res->streamedContent());
    }

    public function test_a_dry_run_says_what_it_would_move_and_moves_nothing(): void
    {
        $att = $this->oldPublicFile($this->job(), 'a photo');

        $this->artisan('attachments:make-private', ['--dry-run' => true])->expectsOutputToContain('would move 1 file(s)')->assertSuccessful();

        $att->refresh()->load('file');
        $this->assertFalse($att->is_private);
        $this->assertSame('public', $att->file->disk);
        Storage::disk('public')->assertExists($att->file->path);
    }

    public function test_running_it_again_finds_nothing_left_to_move(): void
    {
        $this->oldPublicFile($this->job(), 'a photo');
        $this->artisan('attachments:make-private')->assertSuccessful();

        $this->artisan('attachments:make-private')->expectsOutputToContain('moved 0 file(s)')->assertSuccessful();
    }

    public function test_the_files_of_a_deleted_request_and_a_deleted_attachment_go_too(): void
    {
        $gone = $this->job();
        $ofGone = $this->oldPublicFile($gone, 'photo of a deleted job');
        $gone->delete();

        $removed = $this->oldPublicFile($this->job(), 'a removed photo');
        $removed->delete();

        $this->artisan('attachments:make-private')->expectsOutputToContain('moved 2 file(s)')->assertSuccessful();

        $this->assertMovedToPrivate(Attachment::withTrashed()->findOrFail($ofGone->id), 'photo of a deleted job');
        $this->assertMovedToPrivate(Attachment::withTrashed()->findOrFail($removed->id), 'a removed photo');
    }

    public function test_an_assets_files_stay_public(): void
    {
        $att = $this->oldPublicFile(Asset::factory()->create(), 'a hero image');

        $this->artisan('attachments:make-private')
            ->expectsOutputToContain('moved 0 file(s)')
            ->doesntExpectOutputToContain('stay public') // not even looked at: it was never a request's file
            ->assertSuccessful();

        $this->assertSame('public', $att->refresh()->file->disk);
        Storage::disk('public')->assertExists($att->file->path);
    }

    public function test_a_file_that_cannot_be_moved_is_named_kept_where_it_is_and_fails_the_run(): void
    {
        $att = $this->oldPublicFile($this->job(), 'a photo');
        $private = Mockery::mock(FilesystemAdapter::class);
        $private->shouldReceive('writeStream')->andReturnFalse(); // a full or read-only disk
        Storage::set('local', $private);

        $this->artisan('attachments:make-private')
            ->expectsOutputToContain("File {$att->file_id} was not moved")
            ->assertFailed();

        $att->refresh()->load('file');
        $this->assertFalse($att->is_private);
        $this->assertSame('public', $att->file->disk);
        $this->assertSame('a photo', Storage::disk('public')->get($att->file->path), 'the public copy is still what the rows point at');
    }

    public function test_a_file_a_request_shares_with_an_asset_stays_public_and_is_named(): void
    {
        $att = $this->oldPublicFile($this->job(), 'shared bytes');
        $this->attach(Asset::factory()->create(), $att->file);

        $this->artisan('attachments:make-private')
            ->expectsOutputToContain('stay public: '.$att->file_id)
            ->assertSuccessful();

        $this->assertSame('public', $att->refresh()->file->disk);
    }

    public function test_a_file_whose_bytes_are_gone_is_named_and_left_as_it_is(): void
    {
        $att = $this->oldPublicFile($this->job(), 'lost photo');
        Storage::disk('public')->delete($att->file->path);

        $this->artisan('attachments:make-private')
            ->expectsOutputToContain('left as they are: '.$att->file_id)
            ->assertSuccessful();

        $this->assertSame('public', $att->refresh()->file->disk);
    }

    public function test_an_attachment_marked_public_on_a_file_already_private_is_marked_private(): void
    {
        // an old upload of the same bytes could point a public attachment at a file on the private disk: its address opened nothing
        Storage::disk('local')->put('maintenance/1/x.jpg', 'bytes');
        $file = File::create(['path' => 'maintenance/1/x.jpg', 'disk' => File::PRIVATE_DISK, 'mime' => 'image/jpeg', 'size' => 5]);
        $att = $this->attach($this->job(), $file);

        $this->artisan('attachments:make-private')->expectsOutputToContain('Marked 1 attachment(s) private')->assertSuccessful();

        $this->assertTrue($att->refresh()->is_private);
    }
}
