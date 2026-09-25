<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A request's status moves one way only: through MaintenanceTransitionService, after the same permission check as the
 * button for that step. `PUT /maintenance/requests/{id}` and `PUT /api/repair-requests/{id}` accept a `status` too, and
 * used to write it straight into the row — before the service looked at it — so the state map, the timestamps, the SLA
 * pause and the "only the reporter approves" rule were all skipped. `POST /api/repair-requests/{id}/transition` checked
 * the map but let any assigned worker do any step (incl. closing a job on the reporter's behalf).
 */
class RequestStatusPathTest extends TestCase
{
    use RefreshDatabase;

    private function job(string $status, User $reporter, array $extra = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($extra + [
            'status' => $status,
            'reporter_id' => $reporter->id,
            'technician_id' => null,
            'request_date' => now()->subDay(),
            'sla_due_date' => now()->addDay(),
            'paused_duration_minutes' => 0,
            'on_hold_at' => null,
            'acknowledged_at' => null,
            'accepted_at' => null,
            'started_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'completed_date' => null,
            'asset_id' => Asset::factory()->create(['asset_code' => 'S-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null])->id,
        ]);
    }

    private function onTeam(MaintenanceRequest $req, User $user): void
    {
        MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $user->id, 'status' => 'in_progress', 'is_lead' => false]);
    }

    private function putAs(User $as, MaintenanceRequest $req, array $body)
    {
        return $this->actingAs($as)->putJson("/api/repair-requests/{$req->id}", $body);
    }

    public function test_a_technician_cannot_close_a_job_through_put(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $tech = User::factory()->create(['role' => 'it_support']);

        $resolved = $this->job('resolved', $member, ['resolved_at' => now()]);
        $this->onTeam($resolved, $tech);
        $this->putAs($tech, $resolved, ['status' => 'closed'])->assertForbidden();
        $this->assertSame('resolved', $resolved->fresh()->status, 'only the reporter (or an admin) approves the result');

        $working = $this->job('in_progress', $member, ['started_at' => now()]);
        $this->onTeam($working, $tech);
        $this->putAs($tech, $working, ['status' => 'closed'])->assertForbidden();
        $fresh = $working->fresh();
        $this->assertSame('in_progress', $fresh->status, 'a job cannot skip "resolved"');
        $this->assertNull($fresh->closed_at);
    }

    public function test_put_refuses_a_move_the_state_map_forbids_and_keeps_the_rest_of_the_edit_out(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $closed = $this->job('closed', User::factory()->create(['role' => 'member']), ['closed_at' => now(), 'title' => 'original']);

        $this->putAs($admin, $closed, ['title' => 'changed', 'status' => 'pending'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'สถานะไม่ถูกต้อง');

        $fresh = $closed->fresh();
        $this->assertSame('closed', $fresh->status, 'a finished job is not reopened by an edit');
        $this->assertSame('original', $fresh->title, 'a refused edit changes nothing');
    }

    public function test_a_status_change_through_put_is_a_real_transition(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $req = $this->job('in_progress', User::factory()->create(['role' => 'member']), ['started_at' => now()->subHour()]);

        $this->putAs($admin, $req, ['status' => 'resolved'])->assertOk();

        $fresh = $req->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertNotNull($fresh->resolved_at, 'the finish time is stamped');
        $this->assertSame($admin->id, (int) $fresh->status_updated_by, 'who moved it is recorded');

        $log = MaintenanceLog::where('request_id', $req->id)->latest('id')->first();
        $this->assertSame(['in_progress', 'resolved'], [$log->from_status, $log->to_status], 'the history says where it came from');
    }

    public function test_resuming_through_put_counts_the_time_on_hold(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $due = now()->addDay()->startOfSecond();
        // a job that is on hold has somebody in charge of it (one with nobody cannot be resumed: see StartNeedsAWorkerTest)
        $tech = User::factory()->create(['role' => 'it_support']);
        $req = $this->job('on_hold', User::factory()->create(['role' => 'member']), [
            'started_at' => now()->subDay(), 'on_hold_at' => now()->subHours(3), 'sla_due_date' => $due, 'technician_id' => $tech->id,
        ]);

        $this->putAs($admin, $req, ['status' => 'in_progress'])->assertOk();

        $fresh = $req->fresh();
        $this->assertEqualsWithDelta(180, $fresh->paused_duration_minutes, 1, 'three hours on hold');
        $this->assertNull($fresh->on_hold_at, 'no longer on hold');
        $this->assertEqualsWithDelta(180, $due->diffInMinutes($fresh->sla_due_date), 1, 'the SLA deadline moves out by the paused time');
    }

    public function test_a_reporter_cannot_cancel_a_pending_job_through_put(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $req = $this->job('pending', $member);

        // the same rule as the cancel button: a reporter may cancel once staff have taken the job
        $this->putAs($member, $req, ['status' => 'cancelled'])->assertForbidden();
        $this->assertSame('pending', $req->fresh()->status);
    }

    public function test_the_generic_transition_endpoint_asks_the_same_permission_as_the_buttons(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $tech = User::factory()->create(['role' => 'it_support']);
        $admin = User::factory()->create(['role' => 'admin']);

        $resolved = $this->job('resolved', $member, ['resolved_at' => now()]);
        $this->onTeam($resolved, $tech);

        $this->actingAs($tech)->postJson("/api/repair-requests/{$resolved->id}/transition", ['status' => 'closed'])->assertForbidden();
        $this->assertSame('resolved', $resolved->fresh()->status);

        $this->actingAs($admin)->postJson("/api/repair-requests/{$resolved->id}/transition", ['status' => 'closed'])->assertOk();
        $this->assertSame('closed', $resolved->fresh()->status, 'an admin still can');
    }

    public function test_the_team_still_does_its_own_steps_through_the_api(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $tech = User::factory()->create(['role' => 'it_support']);
        $req = $this->job('in_progress', $member, ['started_at' => now()]);
        $this->onTeam($req, $tech);

        $this->actingAs($tech)->postJson("/api/repair-requests/{$req->id}/transition", ['status' => 'on_hold', 'note' => 'waiting for a part'])->assertOk();
        $this->assertSame('on_hold', $req->fresh()->status);

        $this->actingAs($tech)->postJson("/api/repair-requests/{$req->id}/transition", ['status' => 'in_progress'])->assertOk();
        $this->assertSame('in_progress', $req->fresh()->status);

        $this->actingAs($tech)->postJson("/api/repair-requests/{$req->id}/transition", ['status' => 'resolved', 'note' => 'done'])->assertOk();
        $this->assertSame('resolved', $req->fresh()->status);
    }
}
