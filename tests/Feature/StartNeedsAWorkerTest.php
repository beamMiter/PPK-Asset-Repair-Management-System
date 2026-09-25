<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRating;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * An admin who pressed "รับเรื่อง" / "ดำเนินการ" on a job with no team became its technician, so the reporter's rating was credited to a
 * person the technician board never lists. The lead is now a worker: whoever presses start if they are one, otherwise the first worker
 * on the team — and a job with no worker cannot be started.
 */
class StartNeedsAWorkerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $sup;

    private User $tech;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->sup = User::factory()->create(['role' => 'supervisor']);
        $this->tech = User::factory()->create(['role' => 'it_support', 'name' => 'ช่างเอ']);
        $this->member = User::factory()->create(['role' => 'member']);
    }

    private function job(string $status = 'pending', array $extra = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($extra + [
            'status' => $status, 'reporter_id' => $this->member->id, 'technician_id' => null, 'request_date' => now()->subDays(2),
            'asset_id' => Asset::factory()->create(['his_asset_id' => null])->id,
        ]);
    }

    private function step(User $as, string $action, MaintenanceRequest $req, array $body = [])
    {
        return $this->actingAs($as)->post(route("maintenance.requests.$action", $req), $body);
    }

    private function team(MaintenanceRequest $req): array
    {
        return MaintenanceAssignment::where('maintenance_request_id', $req->id)->where('status', '!=', 'cancelled')
            ->orderBy('id')->get()->map(fn ($a) => "{$a->user->name}:" . ($a->is_lead ? 'lead' : 'member'))->all();
    }

    public function test_an_admin_cannot_start_a_job_that_nobody_works_on(): void
    {
        $req = $this->job('acknowledged');
        $this->step($this->admin, 'accept', $req);
        $this->assertSame('accepted', $req->fresh()->status);

        $this->step($this->admin, 'start', $req)->assertRedirect();

        $this->assertSame('ต้องมอบหมายเจ้าหน้าที่ซ่อมบำรุงให้ใบงานนี้ก่อนเริ่มดำเนินการ', session('toast.message'));
        $fresh = $req->fresh();
        $this->assertSame('accepted', $fresh->status, 'not started');
        $this->assertNull($fresh->technician_id, 'the admin did not become the technician');
        $this->assertSame([], $this->team($req), 'nor was the admin put on the team');
    }

    public function test_an_admin_starting_a_job_makes_the_workers_on_it_the_lead_not_themselves(): void
    {
        $req = $this->job('acknowledged');
        $this->step($this->tech, 'accept', $req);                  // the worker takes it (no lead yet)
        $this->assertNull($req->fresh()->technician_id);

        $this->step($this->admin, 'start', $req);                  // an admin presses start for them

        $fresh = $req->fresh();
        $this->assertSame('in_progress', $fresh->status);
        $this->assertSame($this->tech->id, $fresh->technician_id);
        $this->assertSame(['ช่างเอ:lead'], $this->team($req), 'the worker leads; the admin is not on the team');
    }

    public function test_a_worker_who_starts_a_job_is_still_its_lead(): void
    {
        $req = $this->job('acknowledged');
        $this->step($this->tech, 'accept', $req);
        $this->step($this->tech, 'start', $req);

        $this->assertSame($this->tech->id, $req->fresh()->technician_id);
        $this->assertSame(['ช่างเอ:lead'], $this->team($req));
    }

    public function test_resuming_a_held_job_puts_a_worker_in_charge_too(): void
    {
        $req = $this->job('acknowledged');
        $this->step($this->tech, 'accept', $req);
        $this->step($this->tech, 'hold', $req, ['note' => 'รออะไหล่']);
        $this->assertSame('on_hold', $req->fresh()->status);

        $this->step($this->admin, 'resume', $req);

        $this->assertSame($this->tech->id, $req->fresh()->technician_id);
        $this->assertSame('in_progress', $req->fresh()->status);
    }

    public function test_accepting_through_the_edit_form_as_an_admin_does_not_make_them_the_technician(): void
    {
        // (a worker cannot use the edit form on a job that is not theirs yet: they accept with the button)
        $byAdmin = $this->job('acknowledged');
        $this->actingAs($this->admin)->putJson(route('maintenance.requests.update', ['req' => $byAdmin->id]), ['status' => 'accepted'])->assertOk();

        $this->assertSame('accepted', $byAdmin->fresh()->status);
        $this->assertNull($byAdmin->fresh()->technician_id);

        $bySupervisor = $this->job('acknowledged');
        $this->actingAs($this->sup)->putJson(route('maintenance.requests.update', ['req' => $bySupervisor->id]), ['status' => 'accepted'])->assertOk();
        $this->assertNull($bySupervisor->fresh()->technician_id);
    }

    public function test_the_rating_of_a_job_an_admin_pushed_through_goes_to_the_worker(): void
    {
        $req = $this->job('acknowledged');
        $this->step($this->tech, 'accept', $req);
        $this->step($this->admin, 'start', $req);
        $this->step($this->admin, 'resolve', $req, ['resolution_note' => 'เสร็จ']);
        $this->step($this->member, 'close', $req);
        $this->assertSame('closed', $req->fresh()->status);
        $this->travel(1)->minutes();   // the column keeps whole seconds and rounds up: a rating in the same instant is "in the future"

        $this->actingAs($this->member)->post(route('maintenance.requests.rating.store', $req), ['score' => 5, 'comment' => 'ดี']);

        $this->assertSame($this->tech->id, MaintenanceRating::where('maintenance_request_id', $req->id)->value('technician_id'));
    }
}
