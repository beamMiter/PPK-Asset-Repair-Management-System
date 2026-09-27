<?php

namespace Tests\Feature\Ui;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "ประวัติการแจ้งซ่อมล่าสุด" on an asset's own page was a leftover, hand-built card, inline in the page body: English labels
 * ("Job ID", "Problem Description", "Technician", "View Details") next to Thai ones everywhere else, three separate colour-map
 * closures for one status badge, a hand-drawn crc32-hashed initials avatar, and `$mr->ticket_no` (not a real column, so it always
 * fell back to the row's plain id instead of the request number). After three inline redesigns of its own, it moved to match the
 * job page exactly: a "ประวัติการแจ้งซ่อม" icon button with a count, top-right of the header, opening the same dot / connecting-line
 * timeline behind that page's own "history" icon (`partials/_timeline.blade.php`) - one item per request instead of per status
 * change, the 5 most recent, with "ดูประวัติการแจ้งซ่อมทั้งหมด" for the rest.
 */
class AssetRepairHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_the_header_carries_the_history_button_with_a_count(): void
    {
        $asset = Asset::factory()->create();
        MaintenanceRequest::factory()->count(3)->create(['asset_id' => $asset->id]);

        $html = $this->actingAs($this->admin())->get(route('assets.show', $asset))->assertOk()->getContent();

        $this->assertStringContainsString('ประวัติการแจ้งซ่อม', $html);
        $this->assertMatchesRegularExpression('/ประวัติการแจ้งซ่อม\s*<span[^>]*>3<\/span>/u', $html, 'the count badge, like the job page\'s own history button');
        $this->assertStringContainsString('showHistory = true', $html, 'the button opens the modal');
    }

    public function test_the_timeline_shows_the_five_most_recent_requests_newest_first(): void
    {
        $asset = Asset::factory()->create();
        $requests = collect(range(1, 6))->map(fn ($i) => MaintenanceRequest::factory()->create([
            'asset_id' => $asset->id,
            'title' => "งานทดสอบเลขที่{$i}",
            'created_at' => now()->subDays(6 - $i),
        ]));
        $oldest = $requests->first();   // งานทดสอบเลขที่1, created 5 days ago: the 6th most recent, dropped
        $newest = $requests->last();    // งานทดสอบเลขที่6, created today: the most recent, shown first

        $html = $this->actingAs($this->admin())->get(route('assets.show', $asset))->assertOk()->getContent();

        $this->assertSame(5, substr_count($html, 'งานทดสอบเลขที่'), 'only the 5 most recent, not all 6');
        $this->assertStringNotContainsString($oldest->title, $html);
        $this->assertStringContainsString($newest->title, $html);

        $positions = $requests->skip(1)->map(fn ($r) => strpos($html, $r->title));
        $this->assertSame($positions->sortDesc()->values()->all(), $positions->values()->all(), 'newest first: request 6 before 5 before ... before 2');
    }

    public function test_an_item_names_its_request_number_status_and_date(): void
    {
        $asset = Asset::factory()->create();
        $req = MaintenanceRequest::factory()->create([
            'asset_id' => $asset->id,
            'status' => MaintenanceRequest::STATUS_ON_HOLD,   // the longest status label - see MaintenanceRequest::statusLabels()
            'title' => 'เครื่องเอกซเรย์จอแสดงผลเสีย',
        ]);

        $html = $this->actingAs($this->admin())->get(route('assets.show', $asset))->assertOk()->getContent();

        $this->assertStringContainsString('เครื่องเอกซเรย์จอแสดงผลเสีย', $html);
        $this->assertStringContainsString((string) $req->request_no, $html, 'the real request number, not the row id');
        $this->assertStringContainsString('หยุดการซ่อมบำรุงชั่วคราว', $html);
        $this->assertStringContainsString('text-slate-600">หยุดการซ่อมบำรุงชั่วคราว', $html, 'on_hold is coloured text, same as elsewhere');
        $this->assertStringContainsString(\App\Support\ThaiDate::short($req->created_at), $html);
        $this->assertStringContainsString(route('maintenance.requests.show', $req), $html);
        $this->assertStringContainsString('pause_circle', $html, 'the on_hold dot icon, same as the job page timeline');
        $this->assertStringContainsString(route('maintenance.requests.index', ['asset_id' => $asset->id]), $html, '"ดูประวัติการแจ้งซ่อมทั้งหมด" points at the real list');

        foreach (['Job ID', 'Problem Description', '>Technician<', 'View Details'] as $leftover) {
            $this->assertStringNotContainsString($leftover, $html, "\"{$leftover}\": old hand-built card left over");
        }
        $this->assertStringNotContainsString('crc32', $html);
    }

    public function test_no_line_in_the_page_separates_text_with_a_middle_dot(): void
    {
        // NoDotSeparatorsTest already checks every view app-wide; this pins the one line this feature added
        $asset = Asset::factory()->create();
        MaintenanceRequest::factory()->create(['asset_id' => $asset->id]);

        $html = $this->actingAs($this->admin())->get(route('assets.show', $asset))->assertOk()->getContent();

        $this->assertStringNotContainsString('·', $html);
    }

    public function test_an_asset_with_no_request_at_all_shows_the_empty_state_in_the_modal(): void
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
