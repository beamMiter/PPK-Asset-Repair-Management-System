<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The hero of the Main Dashboard (repair/dashboard.blade.php) — every role's landing page after login
 * (RouteServiceProvider::HOME = '/dashboard', no role branching in DashboardController::index) — had its own
 * "สร้างทะเบียนแจ้งซ่อม" button to the same place ("+ สร้างใบแจ้งซ่อม") the request list page already offers, with its
 * own wording and its own hand-rolled look. A KPI overview page (charts, department/asset breakdowns, technician
 * workload) is not where a create action belongs, and it duplicated a button that already exists one click away.
 * Removed; the hero is now just the title and the "updated" line.
 */
class DashboardHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_hero_has_no_create_request_button_for_any_role(): void
    {
        foreach (['admin', 'member', 'it_support', 'supervisor'] as $role) {
            $html = $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('repair.dashboard'))->assertOk()->getContent();

            $this->assertStringNotContainsString('สร้างทะเบียนแจ้งซ่อม', $html, "$role: no duplicate create button in the hero");
        }
    }

    public function test_creating_a_request_is_still_one_click_from_the_request_list(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('maintenance.requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('maintenance.requests.create'), $html, 'the list page keeps its own create button');
    }
}
