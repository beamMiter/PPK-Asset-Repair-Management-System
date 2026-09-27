<?php

namespace Tests\Feature\Ui;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "ประวัติการแจ้งซ่อมล่าสุด" on an asset's own page was a leftover, hand-built card: English labels ("Job ID", "Problem Description",
 * "Technician", "View Details") next to Thai ones everywhere else, three separate colour-map closures for one badge, a hand-drawn
 * initials avatar instead of the shared `avatar_thumb_url`, and `$mr->ticket_no` (not a real column, so it always fell back to the
 * row's plain id instead of the request number) - and, once first rebuilt on the requests list's own conventions, still a padded
 * multi-row card far bigger than the handful of facts it showed. Settled on one compact row: number + status on one line, the
 * title, then everyone's name on one line of small text; the action is the shared `<x-ui.button size="sm">`.
 */
class AssetRepairHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_the_latest_request_shows_its_real_number_status_reporter_and_technician(): void
    {
        $reporter = User::factory()->create(['name' => 'สมชาย ใจดี']);
        $tech = User::factory()->create(['name' => 'ช่างวิชัย']);
        $asset = Asset::factory()->create();
        $req = MaintenanceRequest::factory()->create([
            'asset_id' => $asset->id,
            'reporter_id' => $reporter->id,
            'status' => MaintenanceRequest::STATUS_IN_PROGRESS,
            'title' => 'เครื่องพิมพ์กระดาษติด',
        ]);
        MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $tech->id, 'is_lead' => true]);

        $html = $this->actingAs($this->admin())->get(route('assets.show', $asset))->assertOk()->getContent();

        $this->assertStringContainsString('#' . $req->request_no, $html, 'the real request number, not the row id');
        $this->assertStringContainsString('เครื่องพิมพ์กระดาษติด', $html);
        $this->assertStringContainsString($req->statusLabel(), $html);
        $this->assertStringContainsString('text-sky-700', $html, 'in_progress is coloured text, same as the requests list');
        $this->assertStringContainsString('สมชาย ใจดี', $html);
        $this->assertStringContainsString('ช่างวิชัย', $html, 'the technician is named on the row');
        $this->assertStringContainsString(route('maintenance.requests.show', $req), $html);

        foreach (['Job ID', 'Problem Description', '>Technician<', 'View Details'] as $leftover) {
            $this->assertStringNotContainsString($leftover, $html, "\"{$leftover}\": old hand-built card left over");
        }
        $this->assertStringNotContainsString('crc32', $html);
    }

    public function test_an_unassigned_request_says_so_instead_of_showing_nobody(): void
    {
        $asset = Asset::factory()->create();
        MaintenanceRequest::factory()->create(['asset_id' => $asset->id]);

        $html = $this->actingAs($this->admin())->get(route('assets.show', $asset))->assertOk()->getContent();

        $this->assertStringContainsString('ยังไม่ได้มอบหมายเจ้าหน้าที่', $html);
    }

    public function test_an_asset_with_no_request_at_all_shows_the_empty_state(): void
    {
        $asset = Asset::factory()->create(['status' => Asset::STATUS_ACTIVE]);

        $this->actingAs($this->admin())->get(route('assets.show', $asset))->assertOk()
            ->assertSee('ยังไม่มีประวัติการแจ้งซ่อม');
    }

    public function test_an_asset_marked_in_repair_with_no_request_offers_to_create_one(): void
    {
        $asset = Asset::factory()->create(['status' => Asset::STATUS_IN_REPAIR]);

        $this->actingAs($this->admin())->get(route('assets.show', $asset))->assertOk()
            ->assertSee('กำลังซ่อม (แต่ไม่พบใบแจ้งซ่อม)')
            ->assertSee('สร้างใบแจ้งซ่อมทันที');
    }
}
