<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\File;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AT1: GET /attachments/{attachment} read path/disk/mime/size straight off
 * the Attachment (which has no such columns), so every private download
 * 404'd. They live on the linked File.
 *
 * These cases are about how the file is streamed, so they read it as an admin;
 * who may read it at all is PrivateAttachmentAccessTest.
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

        $resp = $this->actingAs(User::factory()->create(['role' => 'admin']))
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

        $resp = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('attachments.show', $attachment) . '?download=1');

        $resp->assertOk();
        $this->assertStringContainsString('attachment;', $resp->headers->get('Content-Disposition'));
    }

    public function test_a_thai_file_name_reaches_the_browser_with_an_ascii_fallback(): void
    {
        $attachment = $this->privateAttachment();
        $attachment->update(['original_name' => 'รูปหน้างาน.pdf']);

        $header = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('attachments.show', $attachment))
            ->assertOk()
            ->headers->get('Content-Disposition');

        $this->assertStringContainsString("filename*=utf-8''".rawurlencode('รูปหน้างาน.pdf'), $header);
        $this->assertStringContainsString('filename=file.pdf', $header);
        $this->assertMatchesRegularExpression('/^[\x20-\x7E]+$/', $header, 'a header is plain ASCII');
    }

    #[DataProvider('awkwardNames')]
    public function test_a_file_name_cannot_break_the_header(string $name): void
    {
        $attachment = $this->privateAttachment();
        $attachment->update(['original_name' => $name]);

        $res = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('attachments.show', $attachment))
            ->assertOk();

        $this->assertMatchesRegularExpression('/^[\x20-\x7E]+$/', $res->headers->get('Content-Disposition'));
        $this->assertFalse($res->headers->has('X-Injected'));
    }

    public static function awkwardNames(): array
    {
        return [
            'a line break' => ["evil\r\nX-Injected: 1.pdf"],
            'slashes' => ['a/b\\c.pdf'],
            'a quote' => ['say "hi".pdf'],
            'a percent' => ['100%.pdf'],
            'nothing left' => ["\r\n"],
        ];
    }

    public function test_an_unchanged_file_answers_304_without_its_bytes_and_still_asks_who_is_asking(): void
    {
        $attachment = $this->privateAttachment('secret payload');
        $attachment->file->update(['checksum_sha256' => hash('sha256', 'secret payload')]);
        $url = route('attachments.show', $attachment);
        $admin = User::factory()->create(['role' => 'admin']);

        $etag = $this->actingAs($admin)->get($url)->assertOk()->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $again = $this->actingAs($admin)->get($url, ['If-None-Match' => $etag])->assertStatus(304);
        $this->assertSame('', (string) $again->getContent());

        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->getJson($url, ['If-None-Match' => $etag])
            ->assertForbidden();
    }

    public function test_missing_file_row_is_404_not_500(): void
    {
        $attachment = $this->privateAttachment();
        $attachment->file->delete();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('attachments.show', $attachment))
            ->assertNotFound();
    }
}
