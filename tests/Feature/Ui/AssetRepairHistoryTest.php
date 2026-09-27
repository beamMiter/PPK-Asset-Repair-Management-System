<?php

namespace Tests\Feature\Ui;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "ประวัติการแจ้งซ่อมล่าสุด" on an asset's own page was a leftover, hand-built card: English labels ("Job ID", "Problem Description",
 * "Technician", "View Details") next to Thai ones everywhere else, three separate colour-map closures for one status badge, a
 * hand-drawn crc32-hashed initials avatar, and `$mr->ticket_no` (not a real column, so it always fell back to the row's plain id
 * instead of the request number). Two rebuilds of its own (a compact row, then one spread into columns - the second still wrapped
 * "หยุดการซ่อมบำรุงชั่วคราว", the longest status label, because a fixed-width flex column cannot do what a table column already does)
 * were both still a design of their own. Settled on the one the user actually asked for: the exact `<table>` the requests list
 * (`maintenance/requests/index.blade.php`) uses - same columns (except "หน่วยงาน", which would repeat the one department this
 * page is already about), same classes, same "✅ Center" convention, same shared `<x-ui.button size="sm">` actions - so this reads
 * as that page's own list, not a look-alike.
 */
class AssetRepairHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_the_latest_request_is_a_row_of_the_same_table_the_requests_list_uses(): void
    {
        $reporter = User::factory()->create(['name' => 'สมชาย ใจดี']);
        $asset = Asset::factory()->create();
        $type = MaintenanceRequestType::create(['name' => 'เครื่องมือแพทย์', 'is_active' => true, 'sort_order' => 1]);
        $req = MaintenanceRequest::factory()->create([
            'asset_id' => $asset->id,
            'reporter_id' => $reporter->id,
            'type_id' => $type->id,
            'status' => MaintenanceRequest::STATUS_ON_HOLD,   // the longest status label - see MaintenanceRequest::statusLabels()
            'title' => 'เครื่องเอกซเรย์จอแสดงผลเสีย',
        ]);

        $html = $this->actingAs($this->admin())->get(route('assets.show', $asset))->assertOk()->getContent();

        $this->assertStringContainsString((string) $req->request_no, $html, 'the real request number, not the row id');
        $this->assertStringContainsString('เครื่องเอกซเรย์จอแสดงผลเสีย', $html);
        $this->assertStringContainsString('เครื่องมือแพทย์', $html, 'the request type');
        $this->assertStringContainsString('สมชาย ใจดี', $html);
        $this->assertStringContainsString('หยุดการซ่อมบำรุงชั่วคราว', $html);
        $this->assertStringContainsString('text-slate-600">หยุดการซ่อมบำรุงชั่วคราว', $html, 'on_hold is coloured text, same as the requests list');
        $this->assertStringContainsString(route('maintenance.requests.show', $req), $html);
        $this->assertStringContainsString(route('maintenance.requests.edit', $req), $html, 'an admin may also edit it, like on the requests list');

        // the same table shell the requests list uses, not a card or a flex row of its own
        $this->assertStringContainsString('<table class="min-w-full text-[13px]">', $html);
        foreach (['เลขใบงาน', 'เรื่อง/ปัญหา', 'ประเภทงาน', 'ผู้แจ้ง', 'สถานะ', 'การจัดการ'] as $header) {
            $this->assertStringContainsString('>' . $header . '<', $html, "column header \"{$header}\"");
        }

        foreach (['Job ID', 'Problem Description', '>Technician<', 'View Details'] as $leftover) {
            $this->assertStringNotContainsString($leftover, $html, "\"{$leftover}\": old hand-built card left over");
        }
        $this->assertStringNotContainsString('crc32', $html);
    }

    public function test_a_member_does_not_see_the_edit_action(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $asset = Asset::factory()->create();
        $req = MaintenanceRequest::factory()->create([
            'asset_id' => $asset->id,
            'reporter_id' => User::factory()->create()->id,   // someone other than $member: the factory's own default
            'status' => MaintenanceRequest::STATUS_IN_PROGRESS, // random-picks any existing user, which would be $member alone here
        ]);

        $html = $this->actingAs($member)->get(route('assets.show', $asset))->assertOk()->getContent();

        $this->assertStringContainsString('ดูรายละเอียด', $html);
        $this->assertStringNotContainsString(route('maintenance.requests.edit', $req), $html);
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
