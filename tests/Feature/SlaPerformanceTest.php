<?php

namespace Tests\Feature;

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
