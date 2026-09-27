<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A0: attachments.order_column must accept the negative HERO_ORDER sentinel.
 * While the column was unsignedInteger this insert threw QueryException
 * (SQLSTATE 22003 / 1264 out of range) under MySQL strict mode, so every
 * hero-image upload through AssetController::syncAttachments() 500'd.
 */
class AssetHeroImageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_hero_order_sentinel_is_storable(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->create();

        $file = File::create([
            'path' => "assets/hero/{$asset->id}.png",
            'disk' => 'public',
            'mime' => 'image/png',
            'size' => 1234,
        ]);
        Attachment::create([
            'attachable_type' => $asset->getMorphClass(),
            'attachable_id'   => $asset->id,
            'file_id'         => $file->id,
            'original_name'   => 'hero.png',
            'order_column'    => Attachment::HERO_ORDER,
            'uploaded_by'     => $user->id,
        ]);

        $this->assertDatabaseHas('attachments', [
            'attachable_id' => $asset->id,
            'order_column'  => Attachment::HERO_ORDER,
        ]);
    }

    public function test_updating_an_asset_with_a_hero_image_stores_and_replaces_it(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = Asset::factory()->create();

        $this->actingAs($admin)
            ->put(route('assets.update', $asset), [
                'asset_code' => $asset->asset_code,
                'name'       => $asset->name,
                'hero_image' => UploadedFile::fake()->image('first.jpg', 400, 300),
            ])
            ->assertRedirect(route('assets.show', $asset));

        $first = $asset->fresh()->attachments()
            ->where('order_column', Attachment::HERO_ORDER)->first();

        $this->assertNotNull($first, 'hero image row was not created');
        $this->assertSame('image/jpeg', $first->file->mime);
        Storage::disk('public')->assertExists($first->file->path);
        $this->assertNotNull($asset->fresh()->hero_image_url);

        // Replace: exactly one hero remains and the old row + file are gone.
        $this->actingAs($admin)->put(route('assets.update', $asset), [
            'asset_code' => $asset->asset_code,
            'name'       => $asset->name,
            'hero_image' => UploadedFile::fake()->image('second.jpg', 400, 300),
        ]);

        $heroes = $asset->fresh()->attachments()
            ->where('order_column', Attachment::HERO_ORDER)->get();

        $this->assertCount(1, $heroes, 'there should still be exactly one hero image');
        $this->assertNotSame($first->id, $heroes->first()->id, 'hero should be a fresh row');
        $this->assertNull(Attachment::find($first->id), 'old hero attachment should be deleted');
        Storage::disk('public')->assertMissing($first->file->path);
    }
}
