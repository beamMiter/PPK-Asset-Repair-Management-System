<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `POST /api/repair-requests/{id}/transition` let in only an admin, a supervisor or somebody already on the request. A new request has
 * nobody on it, so no ordinary staff member could take the first steps from the app (acknowledge, accept, reject, send back), although
 * the web's buttons for exactly those steps are open to all staff: the owner's rule is that staff see every job and any of them may
 * take a new one. Now the endpoint asks what the step's own button asks. What stays with the people on the job (hold, resume, resolve,
 * naming the person in charge) stays with them.
 */
class ApiTransitionFollowsEachStepsOwnRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;

    private User $outsider;   // staff, not on any of these requests

    private User $owner;      // staff, on the requests that have somebody on them

    protected function setUp(): void
    {
        parent::setUp();

        $this->reporter = User::factory()->create(['role' => 'member']);
        $this->outsider = User::factory()->create(['role' => 'it_support']);
        $this->owner = User::factory()->create(['role' => 'network']);
    }

    private function job(string $status, array $extra = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($extra + [
            'status' => $status,
            'reporter_id' => $this->reporter->id,
            'technician_id' => null,
            'request_date' => now()->subDay(),
            'sla_due_date' => now()->addDay(),
            'paused_duration_minutes' => 0,
            'on_hold_at' => $status === 'on_hold' ? now()->subHour() : null,
            'acknowledged_at' => null,
            'accepted_at' => null,
            'started_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'completed_date' => null,
            'asset_id' => Asset::factory()->create(['asset_code' => 'T-'.uniqid(), 'serial_number' => uniqid('T'), 'his_asset_id' => null])->id,
        ]);
    }

    /** A job the owner is on: in charge, and on the team. */
    private function ownedJob(string $status): MaintenanceRequest
    {
        $job = $this->job($status, ['technician_id' => $this->owner->id]);
        MaintenanceAssignment::create(['maintenance_request_id' => $job->id, 'user_id' => $this->owner->id, 'status' => 'in_progress', 'is_lead' => true]);

        return $job;
    }

    private function move(User $as, MaintenanceRequest $job, array $body)
    {
        return $this->actingAs($as)->postJson("/api/repair-requests/{$job->id}/transition", $body);
    }

    public function test_staff_who_are_not_on_a_request_take_its_first_steps_through_the_api(): void
    {
        // acknowledge: nobody is on a pending request
        $pending = $this->job('pending');
        $this->move($this->outsider, $pending, ['status' => 'acknowledged'])->assertOk();
        $this->assertSame('acknowledged', $pending->fresh()->status);

        // accept: another member of the staff takes it on, and is on the job from then on, as the web's accept does
        $this->move($this->owner, $pending, ['status' => 'accepted'])->assertOk();
        $this->assertSame('accepted', $pending->fresh()->status);
        $this->assertTrue(
            MaintenanceAssignment::where('maintenance_request_id', $pending->id)->where('user_id', $this->owner->id)->exists(),
            'whoever accepts a request is on its team'
        );

        // reject
        $toReject = $this->job('pending');
        $this->move($this->outsider, $toReject, ['status' => 'rejected', 'note' => 'not ours'])->assertOk();
        $this->assertSame('rejected', $toReject->fresh()->status);

        // send back, with what to fix
        $toReturn = $this->job('acknowledged');
        $this->move($this->outsider, $toReturn, ['status' => 'returned'])->assertStatus(409);
        $this->move($this->outsider, $toReturn, ['status' => 'returned', 'note' => 'attach a photo of the fault'])->assertOk();
        $this->assertSame('returned', $toReturn->fresh()->status);

        // start: an accepted request nobody is on may be started by any of the staff
        $teamless = $this->job('accepted');
        $this->move($this->outsider, $teamless, ['status' => 'in_progress'])->assertOk();
        $fresh = $teamless->fresh();
        $this->assertSame('in_progress', $fresh->status);
        $this->assertSame($this->outsider->id, (int) $fresh->technician_id, 'who started it is in charge of it');
    }

    public function test_what_belongs_to_the_people_on_the_job_stays_with_them(): void
    {
        $working = $this->ownedJob('in_progress');
        $this->move($this->outsider, $working, ['status' => 'on_hold', 'note' => 'waiting for a part'])->assertForbidden();
        $this->move($this->outsider, $working, ['status' => 'resolved', 'note' => 'done'])->assertForbidden();
        $this->assertSame('in_progress', $working->fresh()->status);

        $held = $this->ownedJob('on_hold');
        $this->move($this->outsider, $held, ['status' => 'in_progress'])->assertForbidden();
        $this->assertSame('on_hold', $held->fresh()->status);

        // and the person on it can, as before
        $this->move($this->owner, $working, ['status' => 'on_hold', 'note' => 'waiting for a part'])->assertOk();
        $this->assertSame('on_hold', $working->fresh()->status);
    }

    public function test_a_request_somebody_else_is_in_charge_of_is_theirs_to_take(): void
    {
        $theirs = $this->job('pending', ['technician_id' => $this->owner->id]);

        foreach (['acknowledged', 'rejected'] as $status) {
            $this->move($this->outsider, $theirs, ['status' => $status])->assertForbidden();
        }
        $this->assertSame('pending', $theirs->fresh()->status);

        $this->move($this->owner, $theirs, ['status' => 'acknowledged'])->assertOk();
    }

    public function test_naming_the_person_in_charge_is_still_for_those_who_run_the_job(): void
    {
        $pending = $this->job('acknowledged');

        $this->move($this->outsider, $pending, ['status' => 'accepted', 'technician_id' => $this->owner->id])->assertForbidden();
        $fresh = $pending->fresh();
        $this->assertSame('acknowledged', $fresh->status, 'a refused move changes nothing');
        $this->assertNull($fresh->technician_id);

        // an admin still can
        $admin = User::factory()->create(['role' => 'admin']);
        $this->move($admin, $pending, ['status' => 'accepted', 'technician_id' => $this->owner->id])->assertOk();
        $this->assertSame($this->owner->id, (int) $pending->fresh()->technician_id);
    }

    public function test_a_member_is_still_turned_away(): void
    {
        $pending = $this->job('pending');

        $this->move($this->reporter, $pending, ['status' => 'cancelled'])->assertForbidden();
        $this->move($this->reporter, $pending, ['status' => 'acknowledged'])->assertForbidden();
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_the_endpoint_refuses_exactly_what_the_buttons_refuse(): void
    {
        // every step of the state map, on a request nobody is on and on one the owner is on, asked by staff who are not on it:
        // the answer is 403 exactly when the step's own button would be refused
        $steps = 0;
        foreach (MaintenanceRequest::ALLOWED_TRANSITIONS as $from => $targets) {
            foreach ($targets as $target) {
                foreach ([false, true] as $owned) {
                    $job = $owned ? $this->ownedJob($from) : $this->job($from);
                    $buttonRefuses = $this->outsider->cannot('moveTo', [$job, $target]);

                    $res = $this->move($this->outsider, $job, ['status' => $target, 'note' => 'a reason']);

                    $this->assertSame(
                        $buttonRefuses,
                        $res->status() === 403,
                        "{$from} → {$target} ".($owned ? 'on a request with an owner' : 'on a request with nobody on it').': the button '
                            .($buttonRefuses ? 'refuses' : 'allows').' it, the endpoint answered '.$res->status()
                    );
                    $steps++;
                }
            }
        }

        $this->assertGreaterThan(20, $steps, 'the loop did walk the state map');
    }
}
