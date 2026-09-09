<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssetCreateUpdateTest extends TestCase
{
    use RefreshDatabase;

    /** A1: POST /api/assets must persist an uploaded hero image, not just validate it. */
    public function test_api_store_persists_uploaded_hero_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $resp = $this->post('/api/assets', [
            'asset_code' => 'API-HERO-1',
            'name'       => 'API asset with hero',
            'hero_image' => UploadedFile::fake()->image('hero.png', 300, 200),
        ], ['Accept' => 'application/json']);

        $resp->assertCreated();

        $asset = Asset::where('asset_code', 'API-HERO-1')->firstOrFail();
        $hero  = $asset->attachments()->where('order_column', Attachment::HERO_ORDER)->first();

        $this->assertNotNull($hero, 'hero image was not stored by the API store endpoint');
        Storage::disk('public')->assertExists($hero->file->path);
        $this->assertNotNull($asset->hero_image_url);
    }

    /** A6: extra "files[]" attachments must be recorded on the public disk. */
    public function test_uploaded_gallery_files_are_recorded_on_the_public_disk(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = Asset::factory()->create();

        $this->actingAs($admin)->put(route('assets.update', $asset), [
            'asset_code' => $asset->asset_code,
            'name'       => $asset->name,
            'files'      => [UploadedFile::fake()->create('manual.pdf', 40, 'application/pdf')],
        ])->assertRedirect();

        $att = $asset->fresh()->attachments()
            ->where('order_column', '!=', Attachment::HERO_ORDER)->first();

        $this->assertNotNull($att);
        $this->assertSame('public', $att->file->disk);
        Storage::disk('public')->assertExists($att->file->path);
    }

    /**
     * A2: the live form partial (assets/_form.blade.php) must actually render a
     * warranty_start input — the backend always accepted the field, but after a
     * partial refactor no page emitted it so users could never set it.
     */
    public function test_create_and_edit_forms_render_the_warranty_start_field(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = Asset::factory()->create();

        $this->actingAs($admin)->get(route('assets.create'))
            ->assertOk()->assertSee('name="warranty_start"', false);

        $this->actingAs($admin)->get(route('assets.edit', $asset))
            ->assertOk()->assertSee('name="warranty_start"', false);
    }

    /** A2: warranty_start round-trips through the update path. */
    public function test_warranty_start_is_saved_from_the_edit_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = Asset::factory()->create(['warranty_start' => null]);

        $this->actingAs($admin)->put(route('assets.update', $asset), [
            'asset_code'      => $asset->asset_code,
            'name'            => $asset->name,
            'warranty_start'  => '2024-02-01',
            'warranty_expire' => '2026-02-01',
        ])->assertRedirect(route('assets.show', $asset));

        $this->assertSame('2024-02-01', $asset->fresh()->warranty_start->format('Y-m-d'));
    }

    /** A2: with the field present, after_or_equal:warranty_start is now enforceable. */
    public function test_warranty_expire_before_warranty_start_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = Asset::factory()->create();

        $this->actingAs($admin)->from(route('assets.edit', $asset))
            ->put(route('assets.update', $asset), [
                'asset_code'      => $asset->asset_code,
                'name'            => $asset->name,
                'warranty_start'  => '2024-06-01',
                'warranty_expire' => '2024-01-01',
            ])
            ->assertRedirect(route('assets.edit', $asset))
            ->assertSessionHasErrors('warranty_expire');
    }
}
