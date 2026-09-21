<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The buttons of the job page, driven through the web routes the way a technician uses them. Until now only start / resolve /
 * close were covered — and "รับเรื่อง" hid a bug: a technician who accepted a job was not put on it, so the redirect to the job
 * page was a 403 for him and for every other technician.
 */
class RequestLifecycleEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = User::factory()->create(['role' => 'member']);
    }

    private function job(string $status = 'pending', array $extra = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($extra + [
            'status' => $status,
            'reporter_id' => $this->member->id,
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
            'asset_id' => Asset::factory()->create(['asset_code' => 'L-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null])->id,
        ]);
    }

    private function step(User $as, string $action, MaintenanceRequest $req, array $body = [])
    {
        return $this->actingAs($as)->post(route("maintenance.requests.$action", $req), $body);
    }

    /** @return list<string> "userId:status" of the assignments that still count */
    private function team(MaintenanceRequest $req): array
    {
        return MaintenanceAssignment::where('maintenance_request_id', $req->id)->where('status', '!=', 'cancelled')
            ->orderBy('user_id')->get()->map(fn ($a) => "{$a->user_id}:{$a->status}".($a->is_lead ? ':lead' : ''))->all();
    }

    public function test_a_technician_can_take_a_job_from_pending_to_resolved_on_his_own(): void
    {
        $tech = User::factory()->create(['role' => 'it_support']);
        $req = $this->job();
        $show = route('maintenance.requests.show', $req);

        $this->step($tech, 'acknowledge', $req)->assertRedirect($show);
        $this->assertSame('acknowledged', $req->fresh()->status);

        $this->step($tech, 'accept', $req)->assertRedirect($show);
        $this->assertSame('accepted', $req->fresh()->status);
        $this->assertSame(["{$tech->id}:in_progress"], $this->team($req), 'whoever accepts is on the job');
        $this->actingAs($tech)->get($show)->assertOk();   // the page he is sent to

        $this->step($tech, 'start', $req)->assertRedirect($show);
        $this->step($tech, 'hold', $req, ['note' => 'waiting for a part'])->assertRedirect($show);
        $this->assertSame('on_hold', $req->fresh()->status);
        $this->step($tech, 'resume', $req)->assertRedirect($show);
        $this->assertSame('in_progress', $req->fresh()->status);
        $this->step($tech, 'resolve', $req, ['resolution_note' => 'replaced the part'])->assertRedirect($show);

        $fresh = $req->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertSame(["{$tech->id}:done:lead"], $this->team($req), 'his part of the job is done (he became its lead when he started it)');
    }

    public function test_an_admin_accepting_does_not_become_the_worker(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $req = $this->job('acknowledged');

        $this->step($admin, 'accept', $req);

        $this->assertSame('accepted', $req->fresh()->status);
        $this->assertSame([], $this->team($req));
    }

    public function test_a_job_nobody_is_on_yet_can_be_opened_and_started_by_any_technician(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = User::factory()->create(['role' => 'it_support']);
        $b = User::factory()->create(['role' => 'network']);
        $req = $this->job('acknowledged');
        $this->step($admin, 'accept', $req);   // accepted, no team

        // startWork is open to every technician (self-dispatch), so the job has to be visible to them
        $this->actingAs($a)->get(route('maintenance.requests.show', $req))->assertOk();
        $this->step($b, 'start', $req)->assertRedirect(route('maintenance.requests.show', $req));
        $this->assertSame('in_progress', $req->fresh()->status);

        // once somebody is on it, it is theirs: a technician who is not on it cannot open it
        $this->actingAs($a)->get(route('maintenance.requests.show', $req))->assertRedirect(route('dashboard'));   // "no permission" toast
    }

    public function test_reject_and_cancel_keep_their_reason(): void
    {
        $tech = User::factory()->create(['role' => 'it_support']);

        $pending = $this->job();
        $this->step($tech, 'reject', $pending, ['reject_reason' => 'not ours'])->assertRedirect();
        $this->assertSame('rejected', $pending->fresh()->status);
        $this->assertStringContainsString('not ours', $pending->logs()->latest('id')->first()->note);

        $working = $this->job('in_progress', ['started_at' => now()]);
        MaintenanceAssignment::create(['maintenance_request_id' => $working->id, 'user_id' => $tech->id, 'status' => 'in_progress', 'is_lead' => false]);
        $this->step($tech, 'cancel', $working, ['cancel_reason' => 'duplicate'])->assertRedirect();
        $this->assertSame('cancelled', $working->fresh()->status);
        $this->assertStringContainsString('duplicate', $working->logs()->latest('id')->first()->note);
    }
}
