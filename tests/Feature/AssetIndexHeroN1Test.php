<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A5: the `hero_image_url` append must not run a query per row on the
 * GET /api/assets listing, and eager-loading `attachments` to feed it must
 * not change the serialized response shape.
 */
class AssetIndexHeroN1Test extends TestCase
{
    use RefreshDatabase;

    private function attachHero(Asset $asset, User $uploader): File
    {
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
            'uploaded_by'     => $uploader->id,
        ]);

        return $file;
    }

    public function test_index_resolves_hero_image_url_without_n_plus_1_and_keeps_response_shape(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $assets = Asset::factory()->count(6)->create();
        foreach ($assets as $asset) {
            $this->attachHero($asset, $user);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $resp  = $this->getJson('/api/assets?per_page=50');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $resp->assertOk();

        // Response shape unchanged: the relation loaded only to feed the append
        // must not leak into the serialized rows.
        $this->assertArrayNotHasKey('attachments', $resp->json('data.0'));

        // Every row resolves its hero image from the eager-loaded collection.
        foreach ($resp->json('data') as $row) {
            $this->assertNotNull($row['hero_image_url']);
            $this->assertStringContainsString('assets/hero/', $row['hero_image_url']);
        }

        // Without the eager-load the append fires ~2 queries per row
        // (hero lookup + lazy file), i.e. ~18 for 6 rows. With it the count
        // stays flat regardless of how many rows come back.
        $this->assertLessThan(
            12,
            $count,
            "Query count {$count} suggests the hero_image_url N+1 has regressed"
        );
    }

    public function test_hero_image_url_matches_between_eager_and_lazy_paths(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->create();
        $this->attachHero($asset, $user);

        // Lazy path (no relation loaded).
        $lazy = Asset::find($asset->id)->hero_image_url;

        // Eager path (relation preloaded exactly like AssetController@index).
        $eager = Asset::with(['attachments' => fn ($rel) => $rel
            ->whereHas('file', fn ($f) => $f->where('mime', 'like', 'image/%'))
            ->with('file'),
        ])->find($asset->id)->hero_image_url;

        $this->assertNotNull($lazy);
        $this->assertSame($lazy, $eager);
    }
}
