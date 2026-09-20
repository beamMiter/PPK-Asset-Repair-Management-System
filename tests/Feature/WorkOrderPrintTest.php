<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "พิมพ์ PDF" button on a request opened /maintenance/requests/{id}/work-order, which returned 500 because the
 * controller rendered a view (`maintenance.pdf.work_order`) that had been left under another name.
 */
class WorkOrderPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_reporter_gets_a_pdf(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);
        $req = MaintenanceRequest::factory()->create(['reporter_id' => $reporter->id]);

        $res = $this->actingAs($reporter)->get(route('maintenance.requests.work-order', $req));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_the_html_preview_shows_the_request_and_every_status_label(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (MaintenanceRequest::statusLabels() as $status => $label) {
            $req = MaintenanceRequest::factory()->create(['status' => $status, 'title' => 'Printer jams']);

            $this->actingAs($admin)
                ->get(route('maintenance.requests.work-order', ['req' => $req, 'html' => 1]))
                ->assertOk()
                ->assertSee('Printer jams')
                ->assertSee($label); // "acknowledged" used to print its raw code
        }
    }

    public function test_a_stranger_cannot_print_someone_elses_request(): void
    {
        $req = MaintenanceRequest::factory()->create(['reporter_id' => User::factory()->create(['role' => 'member'])->id]);
        $stranger = User::factory()->create(['role' => 'member']);

        $this->actingAs($stranger)->getJson(route('maintenance.requests.work-order', $req))->assertForbidden();
    }
}
