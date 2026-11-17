<?php

namespace Tests\Feature;

use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * R1: the API rating endpoint must behave like the web one — store one team member on the row (not the raw technician_id column). It used
 * to auto-close a still-RESOLVED request after the rating; since 2026-11-17 a rating comes after the reporter's approval and closes
 * nothing (see RatingOnlyAfterApprovalTest).
 */
class MaintenanceRatingApiStoreTest extends TestCase
{
    use RefreshDatabase;

    /** A request the reporter has approved (closed): the only kind that can be rated. */
    private function resolvedRequest(User $reporter): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create([
            'reporter_id'    => $reporter->id,
            'technician_id'  => null,
            'status'         => MaintenanceRequest::STATUS_CLOSED,
            'resolved_at'    => now()->subDays(2),
            'closed_at'      => now()->subDay(),
            'completed_date' => now()->subDay(),
        ]);
    }

    public function test_store_attributes_to_resolved_technician_and_closes_nothing(): void
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
        $this->assertSame(MaintenanceRequest::STATUS_CLOSED, $mr->fresh()->status, 'it was approved before it could be rated, and rating changes nothing');
        $this->assertFalse(\Illuminate\Support\Facades\DB::table('maintenance_logs')->where('request_id', $mr->id)->where('action', 'transition')->exists(), 'no transition was written');
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
