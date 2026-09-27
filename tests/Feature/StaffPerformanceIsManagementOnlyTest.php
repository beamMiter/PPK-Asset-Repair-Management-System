<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Who is carrying how much, and how long each person takes, is about people — the technician rating board was closed to plain members for
 * that reason, and three doors to the same information were left open: the per-technician stats API, the dashboard's "Technician Workload",
 * and a directory of every user with role and department. Management sees all of it; a plain member sees the service, not the staff.
 */
class StaffPerformanceIsManagementOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_per_technician_stats_are_for_management(): void
    {
        $this->getJson('/api/stats/maintenance/technicians')->assertUnauthorized();

        foreach (['member' => 403, 'admin' => 200, 'supervisor' => 200, 'it_support' => 200, 'technician' => 200] as $role => $status) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/stats/maintenance/technicians')->assertStatus($status);
        }
    }

    public function test_the_totals_that_are_not_about_people_stay_open_to_everyone(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'member']));

        foreach (['/api/stats/summary', '/api/stats/maintenance/status-counts', '/api/stats/assets/by-department'] as $url) {
            $this->getJson($url)->assertOk();
        }
    }

    public function test_a_member_is_offered_the_staff_not_a_directory_of_members(): void
    {
        $tech = User::factory()->create(['role' => 'it_support', 'name' => 'ช่างเอ']);
        $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'หัวหน้าบี']);
        $other = User::factory()->create(['role' => 'member', 'name' => 'สมาชิกคนอื่น']);
        $me = User::factory()->create(['role' => 'member', 'name' => 'ฉันเอง']);

        Sanctum::actingAs($me);
        $names = collect($this->getJson('/api/meta/users')->assertOk()->json('data'))->pluck('name')->all();

        $this->assertContains('ช่างเอ', $names);
        $this->assertContains('หัวหน้าบี', $names);
        $this->assertNotContains('สมาชิกคนอื่น', $names);
        $this->assertNotContains('ฉันเอง', $names);
        $this->assertSame([], $this->getJson('/api/meta/users?role=member')->assertOk()->json('data'), 'asking for members by role finds none');
    }

    public function test_management_still_sees_everybody(): void
    {
        $member = User::factory()->create(['role' => 'member', 'name' => 'สมาชิกคนหนึ่ง']);

        foreach (['admin', 'supervisor'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->assertContains('สมาชิกคนหนึ่ง', collect($this->getJson('/api/meta/users')->assertOk()->json('data'))->pluck('name')->all(), $role);
        }
    }

    public function test_the_dashboard_shows_technician_workload_to_management_only(): void
    {
        $tech = User::factory()->create(['role' => 'it_support', 'name' => 'ช่างเฉพาะกิจ']);
        $member = User::factory()->create(['role' => 'member']);
        MaintenanceRequest::factory()->create([
            'status' => 'in_progress', 'reporter_id' => $member->id, 'technician_id' => $tech->id,
            'asset_id' => Asset::factory()->create(['his_asset_id' => null])->id,
        ]);

        $forMember = $this->actingAs($member)->get('/repair/dashboard')->assertOk();
        $forAdmin = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/repair/dashboard')->assertOk();

        $forAdmin->assertSee('ช่างเฉพาะกิจ')->assertSee('ภาระงานเจ้าหน้าที่แต่ละคน');
        $forMember->assertDontSee('ช่างเฉพาะกิจ')->assertDontSee('ภาระงานเจ้าหน้าที่แต่ละคน');
    }
}
