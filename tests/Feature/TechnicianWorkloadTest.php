<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's "who is carrying how much" counted everything but resolved, closed and cancelled, so a job the
 * technician had turned down (rejected) stayed in their load for good. It now counts the model's own open statuses.
 */
class TechnicianWorkloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_open_jobs_count_towards_a_technicians_load(): void
    {
        $tech = User::factory()->create(['role' => 'technician']);
        foreach (MaintenanceRequest::OPEN_STATUSES as $status) {
            MaintenanceRequest::factory()->create(['status' => $status, 'technician_id' => $tech->id, 'asset_id' => null]);
        }
        foreach (['resolved', 'closed', 'cancelled', 'rejected'] as $status) {
            MaintenanceRequest::factory()->create(['status' => $status, 'technician_id' => $tech->id, 'asset_id' => null]);
        }

        $workload = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('repair.dashboard'))
            ->assertOk()
            ->viewData('techWorkload');

        $this->assertSame(count(MaintenanceRequest::OPEN_STATUSES), collect($workload)->firstWhere('id', $tech->id)['total'] ?? null);
    }
}
