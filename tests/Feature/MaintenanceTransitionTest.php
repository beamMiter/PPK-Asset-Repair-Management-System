<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_transition_request_status(): void
    {
        $tech = User::factory()->create(['role' => User::ROLE_IT_SUPPORT]);
        $req  = MaintenanceRequest::factory()->create([
            'status' => MaintenanceRequest::STATUS_PENDING,
            'technician_id' => $tech->id, // Assign tech first
        ]);

        Sanctum::actingAs($tech);

        // Acknowledge the job
        $respAck = $this->postJson("/api/repair-requests/{$req->id}/transition", [
            'status' => MaintenanceRequest::STATUS_ACKNOWLEDGED,
        ]);
        $respAck->assertOk();
        $this->assertSame(MaintenanceRequest::STATUS_ACKNOWLEDGED, $respAck->json('data.status'));

        // Accept the job
        $resp1 = $this->postJson("/api/repair-requests/{$req->id}/transition", [
            'status' => MaintenanceRequest::STATUS_ACCEPTED,
        ]);
        $resp1->assertOk();
        $this->assertSame(MaintenanceRequest::STATUS_ACCEPTED, $resp1->json('data.status'));

        // Start work
        $resp2 = $this->postJson("/api/repair-requests/{$req->id}/transition", [
            'status' => MaintenanceRequest::STATUS_IN_PROGRESS,
        ]);
        $resp2->assertOk();
        $this->assertSame(MaintenanceRequest::STATUS_IN_PROGRESS, $resp2->json('data.status'));
    }

    public function test_invalid_transition_returns_409_not_500(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $req   = MaintenanceRequest::factory()->create([
            'status' => MaintenanceRequest::STATUS_PENDING,
        ]);

        // pending -> in_progress is not an allowed transition; the service
        // aborts(409). handleAction() must surface that as 409, not a bogus 500.
        $resp = $this->actingAs($admin)->postJson("/maintenance/requests/{$req->id}/start");

        $resp->assertStatus(409);
        $this->assertSame(
            MaintenanceRequest::STATUS_PENDING,
            $req->fresh()->status,
            'status must be unchanged after a rejected transition'
        );
    }
}
