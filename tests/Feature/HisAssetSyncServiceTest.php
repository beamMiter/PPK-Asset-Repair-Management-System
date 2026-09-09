<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Services\HisAssetSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HisAssetSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A6: syncFromHis() previously used updateOrCreate() without ever mapping
     * asset_code — a first-time sync hit the NOT NULL / unique asset_code
     * column and threw. It must now create cleanly and a re-sync must not
     * clobber a human-edited internal code.
     */
    public function test_sync_creates_then_updates_without_clobbering_asset_code(): void
    {
        $svc = app(HisAssetSyncService::class);

        $created = $svc->syncFromHis([
            'asset_no' => 'HIS-9001',
            'name'     => 'Ventilator',
            'brand'    => 'Mindray',
        ]);

        $this->assertSame('HIS-9001', $created->his_asset_id);
        $this->assertSame('HIS-9001', $created->asset_code, 'asset_code should seed from the HIS number on create');

        // A human renames the internal code afterwards.
        $created->update(['asset_code' => 'PPK-ICU-014']);

        $resynced = $svc->syncFromHis([
            'asset_no' => 'HIS-9001',
            'name'     => 'Ventilator (updated)',
            'brand'    => 'Philips',
        ]);

        $this->assertSame($created->id, $resynced->id);
        $this->assertSame('PPK-ICU-014', $resynced->asset_code, 're-sync must not overwrite the internal code');
        $this->assertSame('Ventilator (updated)', $resynced->name);
        $this->assertSame('Philips', $resynced->brand);
        $this->assertSame(1, Asset::where('his_asset_id', 'HIS-9001')->count());
    }
}
