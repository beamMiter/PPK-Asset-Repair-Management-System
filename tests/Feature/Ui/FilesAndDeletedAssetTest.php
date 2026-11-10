<?php

namespace Tests\Feature\Ui;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\File;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The owner's choices of 2026-11-09: a request's files carry no Private / Public badge (they are all private now), an asset's
 * files can be opened from its page, and a request whose asset was deleted keeps it on the edit form.
 */
class FilesAndDeletedAssetTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->reporter = User::factory()->create(['role' => 'member']);
    }

    private function pendingJob(array $extra = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($extra + [
            'status' => 'pending', 'reporter_id' => $this->reporter->id, 'technician_id' => null, 'asset_id' => null,
        ]);
    }

    public function test_a_requests_files_carry_no_private_or_public_badge(): void
    {
        $job = $this->pendingJob();
        $this->actingAs($this->reporter)->post(route('maintenance.requests.attachments', $job), ['files' => [UploadedFile::fake()->image('a.jpg', 10, 10)]]);

        foreach ([route('maintenance.requests.show', $job), route('maintenance.requests.edit', $job)] as $page) {
            $html = $this->actingAs($this->reporter)->get($page)->assertOk()->getContent();
            $this->assertStringContainsString('a.jpg', $html, 'the file is on the page');
            $this->assertDoesNotMatchRegularExpression('/>\s*(Private|Public)\s*</', $html, "no badge on {$page}");
        }
    }

    public function test_an_assets_files_open_from_its_page(): void
    {
        $asset = Asset::factory()->create(['his_asset_id' => null]);
        Storage::disk('public')->put('assets/attachments/manual.pdf', 'pdf');
        $file = File::create(['path' => 'assets/attachments/manual.pdf', 'disk' => 'public', 'mime' => 'application/pdf', 'size' => 3]);
        $att = Attachment::create([
            'attachable_type' => $asset->getMorphClass(), 'attachable_id' => $asset->id, 'file_id' => $file->id, 'original_name' => 'คู่มือ.pdf',
        ]);

        $html = $this->actingAs($this->reporter)->get(route('assets.show', $asset))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<a\s(?:[^>]*\s)?href="'.preg_quote(route('attachments.show', $att), '#').'"[^>]*>\s*คู่มือ\.pdf\s*</a>#u', $html);
    }

    public function test_a_request_whose_asset_was_deleted_keeps_it_on_the_edit_form(): void
    {
        $asset = Asset::factory()->create(['his_asset_id' => null, 'asset_code' => 'GONE-1', 'name' => 'เครื่องพิมพ์']);
        $job = $this->pendingJob(['asset_id' => $asset->id]);
        $asset->delete();

        $html = $this->actingAs($this->reporter)->get(route('maintenance.requests.edit', $job))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<option value="'.$asset->id.'"[^>]*selected[^>]*>\s*GONE-1 - เครื่องพิมพ์ \(ถูกลบแล้ว\)\s*</option>#u', $html);

        // what the form sends back keeps it
        $this->actingAs($this->reporter)->put(route('maintenance.requests.update', $job), ['title' => 'แก้หัวข้อ', 'asset_id' => $asset->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($asset->id, $job->fresh()->asset_id);
    }

    public function test_a_deleted_asset_is_not_offered_on_any_other_request(): void
    {
        Asset::factory()->create(['his_asset_id' => null, 'asset_code' => 'GONE-2'])->delete();

        $this->actingAs($this->reporter)->get(route('maintenance.requests.edit', $this->pendingJob()))->assertOk()->assertDontSee('GONE-2');
        $this->actingAs($this->reporter)->get(route('maintenance.requests.create'))->assertOk()->assertDontSee('GONE-2');
    }
}
