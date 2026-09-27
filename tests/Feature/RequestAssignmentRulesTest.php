<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may change a job's team, and to what. The assign dialog used to accept anybody's name, from any technician, on any
 * job in any status: a technician who was not on a closed job could put himself on it (the real worker was cancelled and
 * the rating followed the newest assignment), an empty submit left an in-progress job with nobody on it, and a plain member
 * account could be put on a team.
 */
class RequestAssignmentRulesTest extends TestCase
{
    use RefreshDatabase;

    private function job(string $status, array $extra = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($extra + [
            'status' => $status,
            'reporter_id' => User::factory()->create(['role' => 'member'])->id,
            'technician_id' => null,
            'request_date' => now()->subDay(),
            'sla_due_date' => now()->addDay(),
            'asset_id' => Asset::factory()->create(['asset_code' => 'A-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null])->id,
        ]);
    }

    private function onTeam(MaintenanceRequest $req, User ...$users): void
    {
        foreach ($users as $u) {
            MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $u->id, 'status' => 'in_progress', 'is_lead' => false]);
        }
    }

    /** @return list<int> */
    private function team(MaintenanceRequest $req): array
    {
        return MaintenanceAssignment::where('maintenance_request_id', $req->id)->where('status', '!=', 'cancelled')->pluck('user_id')->sort()->values()->all();
    }

    private function assign(User $as, MaintenanceRequest $req, array $ids)
    {
        return $this->actingAs($as)->post(route('maintenance.requests.assignments.store', $req), ['user_ids' => $ids]);
    }

    public function test_a_technician_cannot_change_the_team_of_somebody_elses_job(): void
    {
        $owner = User::factory()->create(['role' => 'it_support']);
        $other = User::factory()->create(['role' => 'network']);
        $req = $this->job('in_progress');
        $this->onTeam($req, $owner);

        $this->assign($other, $req, [$other->id]);

        $this->assertSame([$owner->id], $this->team($req), 'a technician who is not on the job cannot take it over');
    }

    public function test_a_technician_on_the_job_can_hand_it_over_and_anyone_can_dispatch_a_job_nobody_is_on(): void
    {
        $owner = User::factory()->create(['role' => 'it_support']);
        $dev = User::factory()->create(['role' => 'programmer']);

        $staffed = $this->job('in_progress');
        $this->onTeam($staffed, $owner);
        $this->assign($owner, $staffed, [$owner->id, $dev->id]);
        $this->assertSame([$owner->id, $dev->id], $this->team($staffed), 'hand-over to a colleague');

        $free = $this->job('acknowledged');
        $this->assign($dev, $free, [$dev->id]);
        $this->assertSame([$dev->id], $this->team($free), 'a technician takes a job that has nobody on it');
    }

    public function test_nobody_changes_the_team_of_a_finished_job(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $worker = User::factory()->create(['role' => 'it_support']);
        $intruder = User::factory()->create(['role' => 'network']);

        foreach (['closed', 'cancelled', 'rejected'] as $status) {
            $req = $this->job($status);
            $this->onTeam($req, $worker);

            $this->assign($intruder, $req, [$intruder->id]);
            $this->assign($admin, $req, [$intruder->id]);

            $this->assertSame([$worker->id], $this->team($req), "a $status job keeps the team that did the work");
        }
    }

    public function test_a_resolved_jobs_team_is_for_admins_and_supervisors_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $worker = User::factory()->create(['role' => 'it_support']);
        $fix = User::factory()->create(['role' => 'network']);
        $req = $this->job('resolved');
        $this->onTeam($req, $worker);

        $this->assign($worker, $req, [$worker->id, $fix->id]);
        $this->assertSame([$worker->id], $this->team($req));

        $this->assign($admin, $req, [$fix->id]);
        $this->assertSame([$fix->id], $this->team($req), 'an admin can still correct who did the work before it is approved');
    }

    public function test_an_empty_submit_cannot_leave_a_job_in_work_with_nobody_on_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = User::factory()->create(['role' => 'it_support']);

        foreach (['accepted', 'in_progress', 'on_hold', 'resolved'] as $status) {
            $req = $this->job($status);
            $this->onTeam($req, $a);

            $this->assign($admin, $req, []);

            $this->assertSame([$a->id], $this->team($req), "a $status job keeps its team when every box is unticked");
        }

        $pending = $this->job('pending');
        $this->onTeam($pending, $a);
        $this->assign($admin, $pending, []);
        $this->assertSame([], $this->team($pending), 'before anybody has taken the job the team can still be cleared');
    }

    public function test_only_team_accounts_can_be_put_on_a_job(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);
        $suspended = User::factory()->create(['role' => 'it_support', 'suspended_at' => now()]);
        $req = $this->job('acknowledged');

        $this->assign($admin, $req, [$member->id]);
        $this->assertSame([], $this->team($req), 'a plain member account is not staff');

        $this->assign($admin, $req, [$suspended->id]);
        $this->assertSame([], $this->team($req), 'nor is a suspended one');
    }

    public function test_the_api_edit_cannot_empty_the_team_of_a_job_in_work_either(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = User::factory()->create(['role' => 'it_support']);
        $req = $this->job('in_progress');
        $this->onTeam($req, $a);

        $this->actingAs($admin)->putJson("/api/repair-requests/{$req->id}", ['title' => 'x', 'user_ids' => []])->assertStatus(422);

        $this->assertSame([$a->id], $this->team($req));
    }
}
