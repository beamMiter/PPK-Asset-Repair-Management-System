<?php

namespace Tests\Feature;

use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest as MR;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The team dialog gave every person it added, or brought back, the status `in_progress` whatever the request's own: an admin adding someone to
 * a request whose repair is done (the one case the policy lets them: "the team of a finished job is its record") left a live "กำลังทำ" row on a
 * finished job. The transition service has always dated a row by the request's status (resolved / closed → done); the dialog now does too.
 * Found by a random-lifecycle check.
 */
class TeamRowsTakeTheJobStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $ann;
    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->ann = User::factory()->create(['role' => 'technician']);
        $this->bob = User::factory()->create(['role' => 'it_support']);
    }

    private function job(string $status): MR
    {
        $job = MR::factory()->create(['status' => $status, 'technician_id' => null, 'asset_id' => null]);
        MaintenanceAssignment::create(['maintenance_request_id' => $job->id, 'user_id' => $this->ann->id, 'role' => 'technician', 'is_lead' => false,
            'status' => $status === 'resolved' ? 'done' : 'in_progress']);

        return $job;
    }

    private function team(MR $job, array $people)
    {
        return $this->actingAs($this->admin)->post(route('maintenance.requests.assignments.store', $job), ['user_ids' => array_map(fn ($u) => $u->id, $people)]);
    }

    private function rowStatus(MR $job, User $who): ?string
    {
        return MaintenanceAssignment::where('maintenance_request_id', $job->id)->where('user_id', $who->id)->value('status');
    }

    public function test_somebody_added_to_a_resolved_request_is_done_not_working(): void
    {
        $job = $this->job('resolved');

        $this->team($job, [$this->ann, $this->bob]);

        $this->assertSame('done', $this->rowStatus($job, $this->bob), 'the repair is over');
        $this->assertSame('done', $this->rowStatus($job, $this->ann));
    }

    public function test_somebody_brought_back_onto_a_resolved_request_is_done_too(): void
    {
        $job = $this->job('resolved');
        MaintenanceAssignment::create(['maintenance_request_id' => $job->id, 'user_id' => $this->bob->id, 'role' => 'it_support', 'is_lead' => false, 'status' => 'cancelled']);

        $this->team($job, [$this->ann, $this->bob]);

        $this->assertSame('done', $this->rowStatus($job, $this->bob));
    }

    public function test_on_a_request_still_being_worked_they_are_working(): void
    {
        foreach (['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold'] as $status) {
            $job = $this->job($status);

            $this->team($job, [$this->ann, $this->bob]);

            $this->assertSame('in_progress', $this->rowStatus($job, $this->bob), $status);
        }
    }
}
