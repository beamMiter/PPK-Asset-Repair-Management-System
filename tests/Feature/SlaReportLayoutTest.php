<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The printed SLA report ran to four pages on the hospital's own sample (a 14 pt body, wide gaps, every summary stacked) and its
 * signature line did not line up with the signature. It has to end on one A4 page for a normal report, and go on to a second page —
 * rather than cut the list — only when the list of late jobs really is long.
 */
class SlaReportLayoutTest extends TestCase
{
    use RefreshDatabase;

    /** A 1x1 transparent PNG: the real one comes from a canvas, ReportSignatureTest covers how it is trimmed. */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';

    /** The department names the hospital really has (the longest is the one a column has to be wide enough for). */
    private const DEPARTMENTS = [
        'ผู้ป่วยนอก', 'ฝ่ายการเงินและบัญชี', 'ห้องปฏิบัติการ', 'เวชศาสตร์ฉุกเฉิน',
        'หอผู้ป่วยใน', 'ฝ่ายบริหารทั่วไป', 'กลุ่มงานเทคโนโลยีสารสนเทศ', 'เภสัชกรรม',
    ];

    /** Titles as long as the real ones: some with spaces, one long enough to need most of its column. */
    private const TITLES = [
        'อินเทอร์เน็ตห้องการเงินหลุดๆ หายๆ', 'รายงานการเงินใน HIS ออกไม่ครบ', 'เครื่องคอมพิวเตอร์ห้องฉุกเฉินทำงานช้ามาก',
        'เปลี่ยนแบตเตอรี่ UPS ห้องเซิร์ฟเวอร์', 'WiFi ตึกผู้ป่วยนอกสัญญาณอ่อน', 'WiFi หอผู้ป่วยในหลุดบ่อย',
        'เปลี่ยนสวิตช์ชั้น 2 ตึกผู้ป่วยนอก', 'แอร์หอผู้ป่วยไม่เย็น มีน้ำหยด',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 9, 24, 10, 0));   // fixed: the report's period starts on 1 January of "this year"
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
        gc_collect_cycles();   // every render builds a whole dompdf document tree; the suite runs in one process
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** $tickets open jobs, all past their SLA deadline, spread over the eight departments. */
    private function lateJobs(int $tickets): void
    {
        $departments = collect(self::DEPARTMENTS)->map(fn ($name) => Department::factory()->create(['name_th' => $name]));
        $statuses = [MaintenanceRequest::STATUS_IN_PROGRESS, MaintenanceRequest::STATUS_ACKNOWLEDGED, MaintenanceRequest::STATUS_PENDING];

        foreach (range(0, $tickets - 1) as $i) {
            MaintenanceRequest::factory()->create([
                'title'         => self::TITLES[$i % count(self::TITLES)],
                'department_id' => $departments[$i % count($departments)]->id,
                'status'        => $statuses[$i % count($statuses)],
                'request_date'  => now()->subDays(5),
                'acknowledged_at' => null, 'accepted_at' => null, 'started_at' => null,
                'resolved_at'   => null, 'closed_at' => null, 'completed_date' => null,
                'sla_due_date'  => now()->subHours($i + 1),
            ]);
        }
    }

    private function pdf(): string
    {
        $resp = $this->actingAs($this->admin())->post(route('maintenance.sla.report'), ['signature' => self::PNG]);
        $resp->assertOk();
        $this->assertSame('application/pdf', $resp->headers->get('content-type'));

        return $resp->getContent();
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page(?!s)\b#', $pdf);
    }

    public function test_a_normal_report_fits_one_a4_page(): void
    {
        $this->lateJobs(16);   // the sample that ran to four pages: 16 late jobs in 8 departments, signed

        $this->assertSame(1, $this->pageCount($this->pdf()));
    }

    public function test_a_long_list_of_late_jobs_runs_on_to_a_second_page_instead_of_being_cut(): void
    {
        $this->lateJobs(30);   // about twice what a page holds

        $this->assertGreaterThan(1, $this->pageCount($this->pdf()));
    }

    /** The screen's data with the two keys the report adds, so what is asserted below is the template's own output. */
    private function reportHtml(): string
    {
        $resp = $this->actingAs($this->admin())->get(route('maintenance.sla.index'))->assertOk();

        return view('maintenance.sla.report', [
            'dashboard'       => $resp->viewData('dashboard'),
            'chartData'       => $resp->viewData('chartData'),
            'breachedTickets' => $resp->viewData('breachedTickets'),
            'periodStart'     => $resp->viewData('periodStart'),
            'periodEnd'       => $resp->viewData('periodEnd'),
            'hospital'        => ['name_th' => 'โรงพยาบาลพระปกเกล้า', 'logo' => public_path('images/logoppk1.png')],
            'reportDate'      => now(),
        ])->render();
    }

    /** The app's locale is en, so Carbon alone printed "24 September 2026" in a Thai document; the SLA page shows พ.ศ. */
    public function test_the_dates_are_thai_with_the_buddhist_year_and_the_period_is_stated(): void
    {
        $this->lateJobs(1);
        $html = $this->reportHtml();

        $this->assertStringContainsString('วันที่ออกรายงาน: 24 กันยายน 2569', $html);
        $this->assertStringContainsString('ช่วงข้อมูล: 1 มกราคม 2569 – 30 กันยายน 2569', $html);
        $this->assertStringNotContainsString('September', $html);
        $this->assertMatchesRegularExpression('#\d\d/\d\d/2569 \d\d:\d\d#', $html, 'due dates in พ.ศ. as well');
    }

    public function test_the_status_column_reads_thai_not_the_raw_status_code(): void
    {
        $this->lateJobs(3);
        $html = $this->reportHtml();

        $this->assertStringContainsString('กำลังดำเนินการ', $html);
        $this->assertStringContainsString('รอดำเนินการ', $html);
        $this->assertStringNotContainsString('In_progress', $html);
        $this->assertStringNotContainsString('Pending', $html);
    }

    public function test_an_empty_list_says_so_instead_of_leaving_the_section_out(): void
    {
        $html = $this->reportHtml();

        $this->assertStringContainsString('ไม่มีงานที่เกินเวลา ณ วันที่ออกรายงาน', $html);
        $this->assertStringContainsString('ไม่มีงานที่เกินเวลาในช่วงข้อมูลนี้', $html);
    }
}
