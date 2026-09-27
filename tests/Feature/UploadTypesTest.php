<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\File;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What may be uploaded, and how an uploaded file is shown. An SVG with a script passed the repair request's attachment rule (`image/*`), and
 * an asset took ANY file (.html, .svg, .php): opened directly, one of those runs its script on the site's own address, as whoever opened it.
 * Now there are two lists (config/uploads.php) - pictures and PDF for a repair request, plus the documents of equipment for an asset - judged
 * by what a file IS; and a private attachment that a browser could run as a page is a download, and whatever is shown is sandboxed.
 */
class UploadTypesTest extends TestCase
{
    use RefreshDatabase;

    private const SVG_WITH_SCRIPT = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.domain)</script></svg>';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    /** @return array<string,array{0:string,1:string}> a name => [file name, content] of files that must never be taken */
    public static function dangerous(): array
    {
        return [
            'svg with a script' => ['logo.svg', self::SVG_WITH_SCRIPT],
            'html page' => ['page.html', '<html><body><script>alert(1)</script></body></html>'],
            'html named as a picture' => ['photo.jpg', '<html><body><script>alert(1)</script></body></html>'],
            'svg named as a picture' => ['photo.png', self::SVG_WITH_SCRIPT],
            'php' => ['shell.php', '<?php system($_GET["c"]); ?>'],
            'xml' => ['data.xml', '<?xml version="1.0"?><a/>'],
        ];
    }

    /**
     * A REAL uploaded file. `UploadedFile::fake()` says what a file is from its NAME (`photo.jpg` is a picture whatever is in it), so a
     * test with it cannot tell a script named as a picture from a picture; a real upload is judged by its content, as the app does.
     */
    private function real(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function request(): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create(['status' => MaintenanceRequest::STATUS_PENDING]);
    }

    // ---- a repair request: pictures and PDF ---------------------------------------------------------------------------

    #[DataProvider('dangerous')]
    public function test_a_repair_request_takes_no_svg_html_or_script_as_an_attachment(string $name, string $content): void
    {
        $req = $this->request();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('maintenance.requests.attachments', $req), ['files' => [$this->real($name, $content)]])
            ->assertStatus(422);

        $this->assertSame(0, $req->attachments()->count());
    }

    public function test_a_repair_request_still_takes_a_picture_and_a_pdf(): void
    {
        $req = $this->request();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('maintenance.requests.attachments', $req), ['files' => [
                UploadedFile::fake()->image('leak.jpg', 300, 200),
                UploadedFile::fake()->image('leak.png', 300, 200),
                UploadedFile::fake()->create('quote.pdf', 40, 'application/pdf'),
            ]])
            ->assertSuccessful();

        $this->assertSame(3, $req->attachments()->count());
    }

    public function test_a_word_document_is_not_a_repair_request_attachment(): void
    {
        $req = $this->request();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('maintenance.requests.attachments', $req), ['files' => [UploadedFile::fake()->create('note.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]])
            ->assertStatus(422);
    }

    // ---- an asset: pictures, PDF and the documents of equipment ---------------------------------------------------------

    #[DataProvider('dangerous')]
    public function test_an_asset_takes_no_svg_html_or_script_either(string $name, string $content): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('assets.update', $asset), [
            'asset_code' => $asset->asset_code,
            'name' => $asset->name,
            'files' => [$this->real($name, $content)],
        ])->assertSessionHasErrors();

        $this->assertSame(0, $asset->fresh()->attachments()->count());
    }

    public function test_an_asset_takes_a_manual_a_sheet_and_a_picture(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $res = $this->postJson('/api/assets', [
            'asset_code' => 'UPL-1',
            'name' => 'เครื่องพิมพ์',
            'files' => [
                UploadedFile::fake()->create('manual.pdf', 40, 'application/pdf'),
                UploadedFile::fake()->create('specs.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                UploadedFile::fake()->create('prices.xlsx', 40, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
                UploadedFile::fake()->image('front.png', 200, 200),
            ],
        ]);

        $res->assertCreated();
        $this->assertSame(5, Asset::where('asset_code', 'UPL-1')->firstOrFail()->attachments()->count());
    }

    public function test_an_asset_refuses_a_zip_and_an_executable(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([['bundle.zip', 'application/zip'], ['setup.exe', 'application/x-msdownload']] as $i => [$name, $mime]) {
            $this->postJson('/api/assets', ['asset_code' => "UPL-Z{$i}", 'name' => 'x', 'files' => [UploadedFile::fake()->create($name, 10, $mime)]])->assertStatus(422);
        }
    }

    public function test_a_hero_image_and_an_avatar_are_never_an_svg(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/assets', ['asset_code' => 'UPL-H', 'name' => 'x', 'hero_image' => $this->real('hero.svg', self::SVG_WITH_SCRIPT)])->assertStatus(422);

        $this->actingAs($admin)->patch(route('profile.update'), [
            'name' => $admin->name, 'email' => $admin->email ?? 'a@example.com', 'avatar' => $this->real('me.svg', self::SVG_WITH_SCRIPT),
        ])->assertSessionHasErrors('avatar');
    }

    // ---- the two lists ---------------------------------------------------------------------------------------------------

    public function test_the_lists_hold_nothing_a_browser_could_run_and_the_asset_list_contains_the_request_list(): void
    {
        foreach (['mimes', 'document_mimes'] as $list) {
            foreach (['svg', 'html', 'htm', 'xml', 'js', 'php', 'exe', 'zip', 'xhtml', 'shtml'] as $bad) {
                $this->assertNotContains($bad, config("uploads.{$list}"), "$list: $bad");
            }
        }
        $this->assertSame([], array_diff(config('uploads.mimes'), config('uploads.document_mimes')));
        $this->assertNull(config('uploads.mimetypes'), 'the old `image/*` list is gone');
    }

    // ---- how a private attachment is shown ---------------------------------------------------------------------------------

    private function stored(string $name, string $mime, string $body = 'x'): Attachment
    {
        $path = "maintenance/private/{$name}";
        Storage::disk('local')->put($path, $body);
        $file = File::create(['path' => $path, 'disk' => 'local', 'mime' => $mime, 'size' => strlen($body)]);
        $mr = MaintenanceRequest::factory()->create();

        return Attachment::create([
            'attachable_type' => $mr->getMorphClass(), 'attachable_id' => $mr->id, 'file_id' => $file->id,
            'original_name' => $name, 'is_private' => true, 'uploaded_by' => User::factory()->create()->id,
        ]);
    }

    private function open(Attachment $attachment, string $query = '')
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('attachments.show', $attachment) . $query)->assertOk();
    }

    public function test_a_picture_is_shown_inline_but_sandboxed(): void
    {
        $res = $this->open($this->stored('leak.png', 'image/png'));

        $this->assertStringContainsString('inline;', $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', $res->headers->get('Content-Security-Policy'));
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_a_pdf_is_shown_inline_and_not_sandboxed_because_the_viewer_needs_that(): void
    {
        $res = $this->open($this->stored('quote.pdf', 'application/pdf'));

        $this->assertStringContainsString('inline;', $res->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('sandbox', (string) $res->headers->get('Content-Security-Policy'), 'only the app\'s own frame rules');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function runnable(): array
    {
        return ['svg' => ['logo.svg', 'image/svg+xml'], 'html' => ['page.html', 'text/html'], 'xml' => ['a.xml', 'application/xml'], 'xhtml' => ['a.xhtml', 'application/xhtml+xml'], 'unknown' => ['blob.bin', 'application/octet-stream']];
    }

    #[DataProvider('runnable')]
    public function test_a_type_a_browser_could_run_is_a_download_never_a_page(string $name, string $mime): void
    {
        $res = $this->open($this->stored($name, $mime, self::SVG_WITH_SCRIPT));

        $this->assertStringContainsString('attachment;', $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', $res->headers->get('Content-Security-Policy'));
    }

    public function test_plain_text_can_still_be_read_in_the_page_and_a_charset_is_not_a_way_round(): void
    {
        $this->assertStringContainsString('inline;', $this->open($this->stored('a.txt', 'text/plain'))->headers->get('Content-Disposition'));
        $this->assertStringContainsString('attachment;', $this->open($this->stored('b.html', 'text/html; charset=utf-8'))->headers->get('Content-Disposition'));
    }

    public function test_the_download_button_still_forces_a_download_for_a_picture(): void
    {
        $res = $this->open($this->stored('leak.png', 'image/png'), '?download=1');

        $this->assertStringContainsString('attachment;', $res->headers->get('Content-Disposition'));
    }
}
