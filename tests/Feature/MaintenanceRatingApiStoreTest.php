<?php

namespace Tests\Feature;

use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * R1: the API rating endpoint must behave like the web one — attribute the
 * score to the resolved team member (not the raw technician_id column) and
 * auto-close a still-RESOLVED request afterwards.
 */
class MaintenanceRatingApiStoreTest extends TestCase
{
    use RefreshDatabase;

    private function resolvedRequest(User $reporter): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create([
            'reporter_id'    => $reporter->id,
            'technician_id'  => null,
            'status'         => MaintenanceRequest::STATUS_RESOLVED,
            'resolved_at'    => now()->subDay(),
            'closed_at'      => null,
            'completed_date' => null,
        ]);
    }

    public function test_store_attributes_to_resolved_technician_and_auto_closes(): void
    {
        $reporter   = User::factory()->create();
        $technician = User::factory()->create(['role' => 'it_support']);
        $mr         = $this->resolvedRequest($reporter);

        MaintenanceAssignment::create([
            'maintenance_request_id' => $mr->id,
            'user_id'                => $technician->id,
            'role'                   => 'it_support',
            'is_lead'                => true,
            'assigned_at'            => now()->subDay(),
            'status'                 => MaintenanceAssignment::STATUS_DONE,
        ]);

        Sanctum::actingAs($reporter);

        $resp = $this->postJson("/api/repair-requests/{$mr->id}/rating", ['score' => 5]);

        $resp->assertCreated();
        $this->assertDatabaseHas('maintenance_ratings', [
            'maintenance_request_id' => $mr->id,
            'rater_id'               => $reporter->id,
            'technician_id'          => $technician->id,   // resolved, not the null column
            'score'                  => 5,
        ]);
        $this->assertSame(
            MaintenanceRequest::STATUS_CLOSED,
            $mr->fresh()->status,
            'a RESOLVED request should auto-close after the reporter rates it'
        );
    }

    public function test_store_is_rejected_when_no_team_member_is_assigned(): void
    {
        $reporter = User::factory()->create();
        $mr       = $this->resolvedRequest($reporter);

        Sanctum::actingAs($reporter);

        $this->postJson("/api/repair-requests/{$mr->id}/rating", ['score' => 5])
            ->assertStatus(422)
            ->assertJsonPath('message', 'ยังไม่มีการมอบหมายเจ้าหน้าที่ในงานนี้ จึงยังให้คะแนนไม่ได้');

        $this->assertDatabaseCount('maintenance_ratings', 0);
    }

    public function test_low_score_requires_a_comment(): void
    {
        $reporter   = User::factory()->create();
        $technician = User::factory()->create(['role' => 'it_support']);
        $mr         = $this->resolvedRequest($reporter);

        MaintenanceAssignment::create([
            'maintenance_request_id' => $mr->id,
            'user_id'                => $technician->id,
            'role'                   => 'it_support',
            'is_lead'                => true,
            'assigned_at'            => now()->subDay(),
            'status'                 => MaintenanceAssignment::STATUS_DONE,
        ]);

        Sanctum::actingAs($reporter);

        $this->postJson("/api/repair-requests/{$mr->id}/rating", ['score' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('comment');
    }
}
