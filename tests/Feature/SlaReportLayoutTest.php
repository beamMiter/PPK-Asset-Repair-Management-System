<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The printed SLA report ran to four pages on the hospital's own sample (a 14 pt body, wide gaps, every summary stacked) and its
 * signature line did not line up with the signature. Printing every late job cannot always fit one page, so the print dialog lets the
 * user choose which jobs go in; what is chosen has to be honoured, and the paper has to say when it is a selection.
 *
 * The PDF's text is not searchable (dompdf writes glyph codes), so the content is asserted on what the template is handed by the
 * real controller action — captured with a view composer — and on that template rendered to HTML.
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

    /** What the controller handed the report template on the last POST. */
    private ?array $reportData = null;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 9, 24, 10, 0));   // fixed: the report's period starts on 1 January of "this year"
        View::composer('maintenance.sla.report', function ($view) {
            $this->reportData = $view->getData();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
        gc_collect_cycles();   // every render builds a whole dompdf document tree; the suite runs in one process
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'สมหญิง ใจดี']);
    }

    /**
     * $tickets open jobs, all past their SLA deadline, spread over the eight departments; the first is the least overdue (1 h), the
     * last the most, so the report lists them last to first.
     *
     * @return Collection<int, MaintenanceRequest>
     */
    private function lateJobs(int $tickets): Collection
    {
        $departments = collect(self::DEPARTMENTS)->map(fn ($name) => Department::factory()->create(['name_th' => $name]));
        $statuses = [MaintenanceRequest::STATUS_IN_PROGRESS, MaintenanceRequest::STATUS_ACKNOWLEDGED, MaintenanceRequest::STATUS_PENDING];

        return collect(range(0, $tickets - 1))->map(fn ($i) => MaintenanceRequest::factory()->create([
            'title'         => self::TITLES[$i % count(self::TITLES)],
            'department_id' => $departments[$i % count($departments)]->id,
            'status'        => $statuses[$i % count($statuses)],
            'request_date'  => now()->subDays(5),
            'acknowledged_at' => null, 'accepted_at' => null, 'started_at' => null,
            'resolved_at'   => null, 'closed_at' => null, 'completed_date' => null,
            'sla_due_date'  => now()->subHours($i + 1),
        ]));
    }

    /** POST the report the way the print dialog does, and return the response. */
    private function printReport(array $input = [])
    {
        $this->reportData = null;

        return $this->actingAs($this->admin())->post(route('maintenance.sla.report'), $input);
    }

    private function pdf(array $input = []): string
    {
        $resp = $this->printReport($input + ['signature' => self::PNG]);
        $resp->assertOk();
        $this->assertSame('application/pdf', $resp->headers->get('content-type'));

        return $resp->getContent();
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page(?!s)\b#', $pdf);
    }

    /** The jobs the report was handed to list, as request numbers. */
    private function listed(): array
    {
        return array_map(fn ($t) => $t->request_no, $this->reportData['breachedTickets']);
    }

    /** The template rendered with what the last POST gave it, as plain text (the words are spans; tags out, whitespace collapsed). */
    private function text(): string
    {
        $this->assertNotNull($this->reportData, 'the report view was not rendered');

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(view('maintenance.sla.report', $this->reportData)->render()))));
    }

    /* ------------------------------------------------------------------ the page budget */

    public function test_a_report_of_ten_chosen_jobs_fits_one_a4_page(): void
    {
        $jobs = $this->lateJobs(16);   // the sample that ran to four pages: 16 late jobs in 8 departments, signed

        $pdf = $this->pdf(['ticket_filter' => 1, 'tickets' => $jobs->take(10)->pluck('id')->implode(',')]);

        $this->assertSame(1, $this->pageCount($pdf));
    }

    public function test_a_long_list_of_late_jobs_runs_on_to_a_second_page_instead_of_being_cut(): void
    {
        $this->lateJobs(30);   // about twice what a page holds, and no selection: everything is printed

        $this->assertGreaterThan(1, $this->pageCount($this->pdf()));
    }

    /* ------------------------------------------------------------------ choosing the jobs */

    public function test_without_a_selection_every_late_job_is_listed(): void
    {
        $this->lateJobs(5);
        $this->printReport()->assertOk();

        $this->assertCount(5, $this->reportData['breachedTickets']);
        $this->assertSame(5, $this->reportData['breachedTotal']);
    }

    public function test_only_the_chosen_jobs_are_listed_most_overdue_first_and_the_total_is_kept(): void
    {
        $jobs = $this->lateJobs(5);   // due 1 h, 2 h, ... 5 h ago
        $this->printReport(['ticket_filter' => 1, 'tickets' => $jobs[1]->id . ',' . $jobs[3]->id])->assertOk();

        $this->assertSame([$jobs[3]->request_no, $jobs[1]->request_no], $this->listed(), 'the 4 h one is later than the 2 h one');
        $this->assertSame(5, $this->reportData['breachedTotal'], 'the paper has to be able to say "2 of 5"');
    }

    public function test_choosing_none_prints_an_empty_list_not_every_job(): void
    {
        $this->lateJobs(3);
        $this->printReport(['ticket_filter' => 1, 'tickets' => ''])->assertOk();

        $this->assertSame([], $this->listed());
        $this->assertSame(3, $this->reportData['breachedTotal']);
    }

    public function test_an_id_that_is_not_a_late_job_is_ignored(): void
    {
        $jobs = $this->lateJobs(2);
        $this->printReport(['ticket_filter' => 1, 'tickets' => $jobs[0]->id . ',999999'])->assertOk();

        $this->assertSame([$jobs[0]->request_no], $this->listed());
    }

    public function test_a_malformed_selection_or_an_over_long_note_is_turned_down(): void
    {
        $this->lateJobs(1);

        $this->printReport(['ticket_filter' => 1, 'tickets' => '1; DROP TABLE users'])->assertSessionHasErrors('tickets');
        $this->printReport(['note' => str_repeat('ก', 1001)])->assertSessionHasErrors('note');
    }

    /* ------------------------------------------------------------------ what the paper says */

    public function test_a_selection_says_it_is_one(): void
    {
        $jobs = $this->lateJobs(5);
        $this->printReport(['ticket_filter' => 1, 'tickets' => $jobs->take(2)->pluck('id')->implode(',')]);

        $this->assertStringContainsString('แสดง 2 จากงานที่เกินเวลาทั้งหมด 5 รายการ', $this->text());
    }

    public function test_the_whole_list_says_how_many_there_are(): void
    {
        $this->lateJobs(5);
        $this->printReport();

        $text = $this->text();
        $this->assertStringContainsString('ทั้งหมด 5 รายการ', $text);
        $this->assertStringNotContainsString('เลือกพิมพ์เฉพาะบางรายการ', $text);
    }

    public function test_choosing_none_says_so_rather_than_claiming_there_are_no_late_jobs(): void
    {
        $this->lateJobs(3);
        $this->printReport(['ticket_filter' => 1, 'tickets' => '']);

        $text = $this->text();
        $this->assertStringContainsString('ไม่ได้เลือกรายการงานที่เกินเวลามาแสดง', $text);
        $this->assertStringNotContainsString('ไม่มีงานที่เกินเวลา ณ วันที่ออกรายงาน', $text);
    }

    public function test_an_empty_list_says_so_instead_of_leaving_the_section_out(): void
    {
        $this->printReport();

        $text = $this->text();
        $this->assertStringContainsString('ไม่มีงานที่เกินเวลา ณ วันที่ออกรายงาน', $text);
        $this->assertStringContainsString('ไม่มีงานที่เกินเวลาในช่วงข้อมูลนี้', $text);
    }

    public function test_a_note_is_printed_escaped_and_with_its_line_breaks(): void
    {
        $this->printReport(['note' => "รออะไหล่ <script>alert(1)</script>\nติดตามภายในสัปดาห์นี้"]);

        $html = view('maintenance.sla.report', $this->reportData)->render();

        $this->assertStringContainsString('ข้อสังเกต / ข้อเสนอแนะ', $html);
        $this->assertStringNotContainsString('<script', $html, 'markup in a note must not become markup in the report');
        $this->assertStringContainsString('&lt;', $html);
        $this->assertStringContainsString('<br />', $html, 'the second line of the note starts a new line');
    }

    public function test_without_a_note_there_is_no_note_box(): void
    {
        $this->printReport();

        $this->assertStringNotContainsString('ข้อสังเกต / ข้อเสนอแนะ', $this->text());
    }

    public function test_the_preparer_is_named_and_there_is_a_line_for_the_approver(): void
    {
        $this->printReport();
        $text = $this->text();

        $this->assertStringContainsString('จัดทำโดย: สมหญิง ใจดี', $text);
        $this->assertStringContainsString('( สมหญิง ใจดี )', $text, 'the name under the preparer\'s signature');
        $this->assertStringContainsString('ผู้จัดทำรายงาน', $text);
        $this->assertStringContainsString('ผู้อนุมัติ', $text);
    }

    public function test_the_report_says_how_its_figures_are_worked_out(): void
    {
        $this->printReport();

        $text = $this->text();
        $this->assertStringContainsString('วิธีคิด: อัตราบรรลุ SLA', $text);
        $this->assertStringContainsString('ไม่จำกัดตามช่วงข้อมูล', $text, 'the late-jobs list is a snapshot, not the period');
    }

    public function test_the_total_of_jobs_in_the_period_is_stated(): void
    {
        $this->lateJobs(4);   // four open jobs in the period, all late
        $this->printReport();

        $this->assertMatchesRegularExpression('/\b4 งานทั้งหมดในช่วง/u', $this->text());
    }

    /** The app's locale is en, so Carbon alone printed "24 September 2026" in a Thai document; the SLA page shows พ.ศ. */
    public function test_the_dates_are_thai_with_the_buddhist_year_and_the_period_is_stated(): void
    {
        $this->lateJobs(1);
        $this->printReport();
        $text = $this->text();

        $this->assertStringContainsString('วันที่ออกรายงาน: 24 กันยายน 2569 เวลา 10:00 น.', $text);
        $this->assertStringContainsString('ช่วงข้อมูล: 1 มกราคม 2569 – 30 กันยายน 2569', $text);
        $this->assertStringNotContainsString('September', $text);
    }

    public function test_each_late_job_shows_who_has_it_and_how_late_it_is(): void
    {
        $jobs = $this->lateJobs(3);   // 1 h, 2 h, 3 h overdue
        $jobs[0]->update(['technician_id' => User::factory()->create(['name' => 'ธนวัฒน์ แก้วมณี'])->id]);
        $this->printReport();
        $text = $this->text();

        $this->assertStringContainsString('ธนวัฒน์ แก้วมณี', $text);
        $this->assertStringContainsString('ยังไม่ระบุ', $text, 'a job nobody has been given says so');
        $this->assertStringContainsString('+1 ชม. 0 น.', $text);
        $this->assertStringContainsString('+3 ชม. 0 น.', $text);
    }

    public function test_the_status_column_reads_thai_not_the_raw_status_code(): void
    {
        $this->lateJobs(3);
        $this->printReport();
        $text = $this->text();

        $this->assertStringContainsString('กำลังดำเนินการ', $text);
        $this->assertStringContainsString('รอดำเนินการ', $text);
        $this->assertStringNotContainsString('In_progress', $text);
        $this->assertStringNotContainsString('Pending', $text);
    }
}
