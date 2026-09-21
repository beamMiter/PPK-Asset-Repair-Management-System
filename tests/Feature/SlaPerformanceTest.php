<?php

namespace Tests\Feature;

use App\Http\Controllers\Maintenance\SlaPerformanceController;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function compliantResolved(): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create([
            'status'                  => MaintenanceRequest::STATUS_CLOSED,
            'request_date'            => now()->subDays(15),
            'resolved_at'             => now()->subDays(10),
            'sla_due_date'            => now()->subDays(5),   // resolved before due → compliant
            'paused_duration_minutes' => 0,
        ]);
    }

    /**
     * SLA1: the distribution chart's "ทำตาม SLA" slice was seeded with
     * $complianceCount and then incremented again per compliant ticket, so it
     * came out at 2x the real number.
     */
    public function test_distribution_does_not_double_count_compliant_tickets(): void
    {
        $this->compliantResolved();
        $this->compliantResolved();
        $this->compliantResolved();

        $chartData = $this->actingAs($this->admin())
            ->get(route('maintenance.sla.index'))
            ->assertOk()
            ->viewData('chartData');

        // $statusDist order is: ทำตาม SLA, เกินเวลา, มีความเสี่ยง, ตามกำหนด
        $this->assertSame('ทำตาม SLA', $chartData['distribution']['labels'][0]);
        $this->assertSame(3, $chartData['distribution']['data'][0]);
    }

    private function activeTicket(string $title, \Carbon\Carbon $due): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create([
            'title'        => $title,
            'status'       => MaintenanceRequest::STATUS_IN_PROGRESS,
            'request_date' => now()->subDays(3),
            'resolved_at'  => null,
            'closed_at'    => null,
            'sla_due_date' => $due,
        ]);
    }

    /**
     * The "เกินเวลา" and "ใกล้ครบกำหนด" tabs used to cut their rows at 20 in the view without a word (the badge said 45, the
     * list held 20). The limit is now one named constant, applied to both tabs, and a cut list says how many it left out.
     * The most overdue / the soonest due are the rows kept.
     */
    public function test_each_tab_is_capped_and_says_how_many_it_left_out(): void
    {
        $limit = SlaPerformanceController::TICKET_LIST_LIMIT;
        $over = $limit + 3;
        $near = $limit + 2;

        foreach (range(1, $over) as $i) {   // BRK-01 is 1 h overdue; the highest number is the most overdue
            $this->activeTicket(sprintf('BRK-%02d', $i), now()->subHours($i));
        }
        foreach (range(1, $near) as $i) {   // RSK-01 is due first; the highest number is the furthest away (still inside the 4 h window)
            $this->activeTicket(sprintf('RSK-%02d', $i), now()->addSeconds(60 + $i * 30));
        }

        $resp = $this->actingAs($this->admin())->get(route('maintenance.sla.index'))->assertOk();
        $html = $resp->getContent();

        $this->assertSame($limit, substr_count($html, 'p-3 border border-red-100 rounded-lg'), 'overdue rows');
        $this->assertSame($limit, substr_count($html, 'p-3 border border-amber-100 rounded-lg'), 'near-due rows');

        // the rows kept are the most overdue and the soonest due
        $resp->assertSee(sprintf('BRK-%02d', $over), false)->assertDontSee('BRK-01', false);
        $resp->assertSee('RSK-01', false)->assertDontSee(sprintf('RSK-%02d', $near), false);

        $this->assertMatchesRegularExpression("/แสดง {$limit} รายการที่เกินเวลานานที่สุด จากทั้งหมด\s+{$over} รายการ/u", $html);
        $this->assertMatchesRegularExpression("/แสดง {$limit} รายการที่ใกล้ครบกำหนดที่สุด จากทั้งหมด\s+{$near} รายการ/u", $html);
        $this->assertSame($limit, $resp->viewData('ticketLimit'));
    }

    /** The cap is only for the screen: the totals and the list the PDF report is built from stay complete. */
    public function test_the_totals_and_the_full_list_are_not_cut(): void
    {
        $over = SlaPerformanceController::TICKET_LIST_LIMIT + 3;

        foreach (range(1, $over) as $i) {
            $this->activeTicket(sprintf('BRK-%02d', $i), now()->subHours($i));
        }

        $resp = $this->actingAs($this->admin())->get(route('maintenance.sla.index'))->assertOk();

        $this->assertCount($over, $resp->viewData('breachedTickets'));
        $this->assertSame($over, $resp->viewData('dashboard')['breached_count']);
    }

    public function test_no_note_when_everything_fits(): void
    {
        $this->activeTicket('BRK-A', now()->subHours(2));
        $this->activeTicket('RSK-A', now()->addMinutes(30));

        $this->actingAs($this->admin())->get(route('maintenance.sla.index'))
            ->assertOk()
            ->assertSee('BRK-A', false)
            ->assertSee('RSK-A', false)
            ->assertDontSee('จากทั้งหมด');
    }

    /** SLA6: the report signature must be an inline image data URI. */
    public function test_report_rejects_a_non_image_signature(): void
    {
        $this->actingAs($this->admin())
            ->from(route('maintenance.sla.index'))
            ->post(route('maintenance.sla.report'), ['signature' => 'https://evil.example/x.png'])
            ->assertRedirect(route('maintenance.sla.index'))
            ->assertSessionHasErrors('signature');
    }

    public function test_report_accepts_an_image_data_uri_signature(): void
    {
        // 1x1 transparent PNG
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';

        $resp = $this->actingAs($this->admin())
            ->post(route('maintenance.sla.report'), ['signature' => $png]);

        $resp->assertOk();
        $this->assertSame('application/pdf', $resp->headers->get('content-type'));
    }
}
