<?php

namespace App\Services;

use App\Models\MaintenanceRequest as MR;
use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MaintenanceAttachmentService
{
    /**
     * แนบไฟล์ต่างๆ ลงในใบงาน (สามารถรับได้หลายไฟล์)
     * รองรับการอัปโหลดผ่านหน้าสร้าง (Store) เปลี่ยนแปลง (Update) หรือหน้า View
     */
    public function attachFiles(MR $req, array $files, array $captions = [], ?int $actorId = null, string $source = 'web', bool $isPrivate = false): array
    {
        $attachedFiles = [];
        foreach ($files as $key => $up) {
            if (!$up instanceof UploadedFile || !$up->isValid()) continue;

            $file = $this->storedFileFor($up, $req, $isPrivate ? 'local' : 'public');

            // ตรวจสอบว่าเคยแนบไฟล์ไปในใบงานนี้แล้วหรือยัง (ดึงที่ถูกลบซ่อนกลับมาด้วยถ้ามี)
            $existing = $req->attachments()->withTrashed()->where('file_id', $file->id)->first();
            $caption  = $captions[$key] ?? null;

            if ($existing) {
                if ($existing->trashed()) $existing->restore();
                
                $existing->fill([
                    'original_name' => $up->getClientOriginalName(),
                    'extension'     => $up->getClientOriginalExtension() ?: $existing->extension,
                    'uploaded_by'   => $actorId,
                    'is_private'    => $isPrivate,
                    'caption'       => $caption ?? $existing->caption,
                ])->save();
                
                $attachedFiles[] = $existing;
                continue;
            }

            $attachment = $req->attachments()->create([
                'file_id'       => $file->id,
                'original_name' => $up->getClientOriginalName(),
                'extension'     => $up->getClientOriginalExtension() ?: null,
                'caption'       => $caption,
                'uploaded_by'   => $actorId,
                'source'        => $source,
                'is_private'    => $isPrivate,
                'order_column'  => 0,
            ]);

            Log::info('[MaintenanceAttachmentService] attached file', [
                'request_id'    => $req->id,
                'file_id'       => $file->id,
                'original_name' => $up->getClientOriginalName(),
            ]);

            $attachedFiles[] = $attachment;
        }

        return $attachedFiles;
    }

    /**
     * The file row for these bytes. A file is kept once per content (`checksum_sha256` is unique, deleted rows included):
     * the bytes are written only when they are not on the disk yet, and a row whose bytes are gone is brought back.
     */
    private function storedFileFor(UploadedFile $up, MR $req, string $disk): File
    {
        $sha  = hash_file('sha256', $up->getRealPath());
        $file = File::withTrashed()->where('checksum_sha256', $sha)->first();

        if ($file && ! $file->trashed() && Storage::disk($file->disk)->exists($file->path)) {
            return $file;
        }

        $path  = $up->store("maintenance/{$req->id}", $disk);
        $attrs = [
            'path'      => $path,
            'disk'      => $disk,
            'mime'      => $up->getClientMimeType(),
            'size'      => $up->getSize(),
            'path_hash' => hash('sha256', $path),
        ];

        if (! $file) {
            return File::create($attrs + ['checksum_sha256' => $sha]);
        }

        $file->fill($attrs)->restore();

        return $file;
    }

    /**
     * ลบไฟล์แนบที่ระบุออกจากใบงาน (ลบรายการแนบและการอ้างอิง)
     */
    public function detachFiles(MR $req, array $attachmentIds, ?int $actorId = null): void
    {
        if (empty($attachmentIds)) return;

        $attachments = $req->attachments()->whereIn('id', $attachmentIds)->get();
        foreach ($attachments as $att) {
            $att->deleteAndCleanup(true);
            
            Log::info('[MaintenanceAttachmentService] detached file', [
                'request_id'    => $req->id,
                'attachment_id' => $att->id,
                'actor_id'      => $actorId
            ]);
        }
    }
}
