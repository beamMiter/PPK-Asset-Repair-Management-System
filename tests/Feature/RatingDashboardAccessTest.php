<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R6: the team-wide Technician Rating dashboard and the per-technician
 * summary leaked every technician's scores + reviewer names + comments to
 * any authenticated user. They now sit behind the same gate as the SLA
 * dashboard (can:maintenance-type-manage). A denied web request is
 * redirected with an error toast (app-wide convention in bootstrap/app.php),
 * not a bare 403.
 */
class RatingDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_member_is_bounced_from_the_technician_dashboard_and_summary(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $tech   = User::factory()->create(['role' => 'it_support']);

        $this->actingAs($member)
            ->get(route('maintenance.requests.rating.technicians'))
            ->assertRedirect();

        $this->actingAs($member)
            ->get(route('technicians.rating.summary', $tech))
            ->assertRedirect();
    }

    public function test_management_can_view_the_technician_dashboard_and_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tech  = User::factory()->create(['role' => 'it_support']);

        $this->actingAs($admin)
            ->get(route('maintenance.requests.rating.technicians'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('technicians.rating.summary', $tech))
            ->assertOk();
    }
}
