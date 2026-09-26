<?php

namespace Tests\Feature\Ui;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bar in the job page's header row ("ทะเบียนแจ้งซ่อม") sets the status buttons (รับทราบ, ดำเนินการ, เสร็จสิ้น …) apart from the tools
 * after them (แก้ไข, พิมพ์ PDF, กลับ). It used to be drawn first in the row whether or not there was a status button to press —
 * on a job with none it was a stray bar at the edge of the row — and it stood before the status buttons, so it separated nothing.
 */
class JobHeaderDividerTest extends TestCase
{
    use RefreshDatabase;

    private const DIVIDER = 'w-px h-6 bg-slate-200 mx-1 hidden sm:block';

    private function page(string $status, User $viewer, ?User $reporter = null): string
    {
        $req = MaintenanceRequest::factory()->create([
            'asset_id' => Asset::factory()->create()->id, 'reporter_id' => ($reporter ?? User::factory()->create(['role' => 'member']))->id,
            'technician_id' => null, 'status' => $status,
        ]);

        return $this->actingAs($viewer)->get(route('maintenance.requests.show', $req))->assertOk()->getContent();
    }

    public function test_with_a_status_button_the_bar_sits_between_the_status_buttons_and_the_tools(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->page('pending', $admin);   // รับทราบ and ไม่รับเรื่อง

        $this->assertSame(1, substr_count($html, self::DIVIDER), 'exactly one bar');
        $bar = strpos($html, self::DIVIDER);
        $this->assertLessThan($bar, strpos($html, 'openRejectModalBtn'), 'after the last status button');
        $this->assertLessThan(strpos($html, 'ยกเลิกการซ่อมบำรุง') ?: PHP_INT_MAX, $bar);
        $this->assertGreaterThan($bar, strpos($html, 'พิมพ์ PDF'), 'before the tools');
    }

    public function test_the_reporters_rate_button_counts_as_a_status_button(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);

        $this->assertSame(1, substr_count($this->page('closed', $reporter, $reporter), self::DIVIDER));
    }

    public function test_with_no_status_button_there_is_no_bar(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // a closed job seen by the admin: nothing to press (only the tools)
        $html = $this->page('closed', $admin);

        $this->assertStringContainsString('พิมพ์ PDF', $html);
        $this->assertStringNotContainsString(self::DIVIDER, $html);
    }

    public function test_a_reporter_whose_request_nobody_has_picked_up_yet_sees_no_bar(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);

        // staff acknowledge and accept it; the reporter can only watch (and edit) — nothing to press
        $this->assertStringNotContainsString(self::DIVIDER, $this->page('pending', $reporter, $reporter));
    }

    public function test_a_reporter_who_can_approve_the_finished_repair_has_a_status_button_and_so_the_bar(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);

        // "อนุมัติปิดงาน" is theirs to press once the repair is done
        $this->assertSame(1, substr_count($this->page('resolved', $reporter, $reporter), self::DIVIDER));
    }
}
