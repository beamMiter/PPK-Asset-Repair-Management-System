<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\File;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * AT1: GET /attachments/{attachment} read path/disk/mime/size straight off
 * the Attachment (which has no such columns), so every private download
 * 404'd. They live on the linked File.
 */
class PrivateAttachmentServeTest extends TestCase
{
    use RefreshDatabase;

    private function privateAttachment(string $body = 'secret payload'): Attachment
    {
        Storage::fake('local');
        $path = 'maintenance/private/note.txt';
        Storage::disk('local')->put($path, $body);

        $file = File::create([
            'path' => $path,
            'disk' => 'local',
            'mime' => 'text/plain',
            'size' => strlen($body),
        ]);

        $mr = MaintenanceRequest::factory()->create();

        return Attachment::create([
            'attachable_type' => $mr->getMorphClass(),
            'attachable_id'   => $mr->id,
            'file_id'         => $file->id,
            'original_name'   => 'note.txt',
            'is_private'      => true,
            'uploaded_by'     => User::factory()->create()->id,
        ]);
    }

    public function test_private_attachment_streams_inline_with_nosniff(): void
    {
        $attachment = $this->privateAttachment('secret payload');

        $resp = $this->actingAs(User::factory()->create())
            ->get(route('attachments.show', $attachment));

        $resp->assertOk();
        $this->assertStringStartsWith('text/plain', $resp->headers->get('Content-Type'));
        $resp->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline;', $resp->headers->get('Content-Disposition'));
        $this->assertSame('secret payload', $resp->streamedContent());
    }

    public function test_download_flag_forces_attachment_disposition(): void
    {
        $attachment = $this->privateAttachment();

        $resp = $this->actingAs(User::factory()->create())
            ->get(route('attachments.show', $attachment) . '?download=1');

        $resp->assertOk();
        $this->assertStringContainsString('attachment;', $resp->headers->get('Content-Disposition'));
    }

    public function test_missing_file_row_is_404_not_500(): void
    {
        $attachment = $this->privateAttachment();
        $attachment->file->delete();

        $this->actingAs(User::factory()->create())
            ->get(route('attachments.show', $attachment))
            ->assertNotFound();
    }
}
