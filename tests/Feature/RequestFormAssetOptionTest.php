<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The asset picker of the request form searches the text of its options, so the HIS registry number (รหัสทะเบียน รพจ) has to be
 * in that text — typing a HIS number used to find nothing, because only "code - name" was rendered.
 */
class RequestFormAssetOptionTest extends TestCase
{
    use RefreshDatabase;

    private function asset(array $attrs): Asset
    {
        return Asset::factory()->create($attrs + ['serial_number' => uniqid('S'), 'status' => 'active']);
    }

    public function test_the_new_request_form_lists_each_asset_with_its_his_number(): void
    {
        $this->asset(['asset_code' => 'AST-001', 'name' => 'เครื่องวัดความดัน', 'his_asset_id' => 'HIS-778899']);
        $this->asset(['asset_code' => 'HIS-5', 'name' => 'เครื่องอัลตราซาวด์', 'his_asset_id' => 'HIS-5']);
        $this->asset(['asset_code' => 'AST-003', 'name' => 'เครื่องพิมพ์', 'his_asset_id' => null]);

        $html = $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('maintenance.requests.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/AST-001 - เครื่องวัดความดัน \(รพจ\. HIS-778899\)/u', $html, 'the HIS number is part of the option text, so it can be searched');
        $this->assertStringContainsString('HIS-5 - เครื่องอัลตราซาวด์', $html);
        $this->assertStringNotContainsString('รพจ. HIS-5)', $html, 'not repeated when it is the asset code itself');
        $this->assertMatchesRegularExpression('/AST-003 - เครื่องพิมพ์\s*<\/option>/u', $html, 'an asset without a HIS number stays as it was');

        // the picker draws the HIS part in the table's colour from `data-his` (layout/widgets.js) — only where the part is shown
        $this->assertSame(1, substr_count($html, 'data-his="'), 'one option carries it');
        $this->assertStringContainsString('data-his="HIS-778899"', $html);
    }

    public function test_the_edit_form_does_too(): void
    {
        $asset = $this->asset(['asset_code' => 'AST-010', 'name' => 'เครื่องช่วยหายใจ', 'his_asset_id' => 'HIS-424242']);
        $req = MaintenanceRequest::factory()->create([
            'status' => 'pending', 'asset_id' => $asset->id, 'technician_id' => null,
            'reporter_id' => User::factory()->create(['role' => 'member'])->id,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('maintenance.requests.edit', $req))
            ->assertOk()
            ->assertSee('AST-010 - เครื่องช่วยหายใจ (รพจ. HIS-424242)', false);
    }
}
