<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest as MR;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "ส่งกลับแก้ไข": before taking a request on, the staff can send it back to the person who filed it, saying what to fix, instead of
 * rejecting it for good. The request keeps its number and its history; the reporter edits it and sends it again. "ไม่รับเรื่อง" is a
 * different case (not our job / out of scope) and stays final. The SLA clock stops while the reporter is fixing it.
 */
class SendBackToReporterTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;
    private User $stranger;
    private User $staff;
    private User $admin;
    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->reporter = User::factory()->create(['role' => 'member']);
        $this->stranger = User::factory()->create(['role' => 'member']);
        $this->staff = User::factory()->create(['role' => 'it_support']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->supervisor = User::factory()->create(['role' => 'supervisor']);
    }

    private function job(string $status = 'pending', array $overrides = []): MR
    {
        return MR::factory()->create(array_merge(['status' => $status, 'reporter_id' => $this->reporter->id, 'technician_id' => null, 'asset_id' => null], $overrides));
    }

    private function sendBack(MR $job, User $by, string $reason = 'ระบุห้องที่เครื่องอยู่ และอาการที่เกิดให้ชัดเจน')
    {
        return $this->actingAs($by)->post(route('maintenance.requests.send-back', $job), ['return_reason' => $reason]);
    }

    public function test_staff_send_a_pending_or_acknowledged_request_back_with_the_reason_in_its_history(): void
    {
        foreach (['pending', 'acknowledged'] as $from) {
            foreach ([$this->staff, $this->admin, $this->supervisor] as $who) {
                $job = $this->job($from);

                $this->sendBack($job, $who, 'ต้องระบุเลขครุภัณฑ์และห้อง');

                $job->refresh();
                $this->assertSame('returned', $job->status, "$from by {$who->role}");
                $this->assertNotNull($job->status_updated_at);
                $log = DB::table('maintenance_logs')->where('request_id', $job->id)->where('to_status', 'returned')->first();
                $this->assertNotNull($log, 'the move is in the history');
                $this->assertStringContainsString('ต้องระบุเลขครุภัณฑ์และห้อง', $log->note);
            }
        }
    }

    public function test_the_reason_is_required_so_the_reporter_is_told_what_to_fix(): void
    {
        $job = $this->job('pending');

        foreach ([null, '', '   '] as $reason) {
            $this->actingAs($this->staff)->post(route('maintenance.requests.send-back', $job), ['return_reason' => $reason])
                ->assertSessionHasErrors('return_reason');
        }

        $this->assertSame('pending', $job->fresh()->status);
    }

    public function test_only_staff_may_send_back_and_only_before_the_job_is_taken_on(): void
    {
        $job = $this->job('pending');
        foreach ([$this->reporter, $this->stranger] as $member) {
            $this->sendBack($job, $member)->assertRedirect(); // refused: back to the page with a toast, nothing changed
        }
        $this->assertSame('pending', $job->fresh()->status);

        foreach (['accepted', 'in_progress', 'on_hold', 'resolved', 'closed', 'cancelled', 'rejected'] as $status) {
            $later = $this->job($status);
            $this->sendBack($later, $this->staff);
            $this->assertSame($status, $later->fresh()->status, "a $status job cannot be sent back");
        }
    }

    public function test_rejecting_is_a_different_case_and_stays_final(): void
    {
        $job = $this->job('pending');

        $this->actingAs($this->staff)->post(route('maintenance.requests.reject', $job), ['reject_reason' => 'not ours']);

        $this->assertSame('rejected', $job->fresh()->status);
        $this->actingAs($this->reporter)->post(route('maintenance.requests.resubmit', $job))->assertRedirect();
        $this->assertSame('rejected', $job->fresh()->status, 'a rejected request cannot be sent again');
    }

    public function test_a_returned_request_is_not_workable_until_it_is_sent_again(): void
    {
        $job = $this->job('returned');

        foreach (['acknowledge', 'accept', 'start', 'hold', 'resolve', 'reject'] as $step) {
            $this->actingAs($this->staff)->post(route("maintenance.requests.$step", $job), ['note' => 'x', 'resolution_note' => 'x', 'reject_reason' => 'x']);
            $this->assertSame('returned', $job->fresh()->status, "$step on a returned request");
        }
        $this->actingAs($this->admin)->postJson("/api/repair-requests/{$job->id}/transition", ['status' => 'accepted'])->assertStatus(409);
        $this->assertSame('returned', $job->fresh()->status);
    }

    public function test_the_reporter_fixes_the_request_and_sends_it_again_under_the_same_number(): void
    {
        $job = $this->job('returned', ['title' => 'before']);
        $number = $job->request_no;

        Sanctum::actingAs($this->reporter);
        $this->putJson("/api/repair-requests/{$job->id}", ['title' => 'after the fix'])->assertOk();
        $this->actingAs($this->reporter)->post(route('maintenance.requests.attachments', $job), ['files' => [UploadedFile::fake()->image('p.jpg', 20, 20)]]);
        $this->assertSame(1, $job->fresh()->attachments()->count(), 'the reporter may add a picture while it is returned');

        $this->actingAs($this->reporter)->post(route('maintenance.requests.resubmit', $job));

        $job->refresh();
        $this->assertSame('pending', $job->status);
        $this->assertSame('after the fix', $job->title);
        $this->assertSame($number, $job->request_no);
        $this->assertTrue(DB::table('maintenance_logs')->where('request_id', $job->id)->where('from_status', 'returned')->where('to_status', 'pending')->exists());

        $this->actingAs($this->staff)->post(route('maintenance.requests.acknowledge', $job));
        $this->assertSame('acknowledged', $job->fresh()->status, 'the staff take it from the queue again');
    }

    public function test_nobody_else_may_edit_or_send_it_again(): void
    {
        $job = $this->job('returned');

        Sanctum::actingAs($this->stranger);
        $this->putJson("/api/repair-requests/{$job->id}", ['title' => 'hijack'])->assertForbidden();
        $this->actingAs($this->stranger)->post(route('maintenance.requests.resubmit', $job))->assertRedirect();
        $this->actingAs($this->staff)->post(route('maintenance.requests.resubmit', $job))->assertRedirect();
        $this->assertSame('returned', $job->fresh()->status);

        $this->actingAs($this->admin)->post(route('maintenance.requests.resubmit', $job));
        $this->assertSame('pending', $job->fresh()->status, 'an admin may send it again on the reporter\'s behalf');
    }

    public function test_a_request_that_is_not_returned_cannot_be_sent_again(): void
    {
        foreach (['pending', 'acknowledged', 'accepted', 'in_progress', 'closed'] as $status) {
            $job = $this->job($status);
            $this->actingAs($this->reporter)->post(route('maintenance.requests.resubmit', $job));
            $this->assertSame($status, $job->fresh()->status);
        }
    }

    public function test_the_reporter_may_give_up_a_returned_request_but_the_staff_may_not_cancel_it(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'SB-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null]);
        $job = $this->job('pending', ['asset_id' => $asset->id]);
        $this->assertSame('in_repair', $asset->fresh()->status);

        $this->sendBack($job, $this->staff);
        $this->assertSame('in_repair', $asset->fresh()->status, 'still reserved while the reporter fixes it, like a pending request');

        $this->actingAs($this->staff)->post(route('maintenance.requests.cancel', $job), ['cancel_reason' => 'x']);
        $this->assertSame('returned', $job->fresh()->status);

        $this->actingAs($this->reporter)->post(route('maintenance.requests.cancel', $job), ['cancel_reason' => 'no longer needed']);
        $this->assertSame('cancelled', $job->fresh()->status);
        $this->assertSame('active', $asset->fresh()->status, 'cancelling frees the asset');
    }

    public function test_the_sla_clock_stops_while_it_is_with_the_reporter(): void
    {
        $due = now()->addDays(3)->startOfSecond();
        $respond = now()->addHours(4)->startOfSecond();

        // never acknowledged: both deadlines move by the two days it was away
        $first = $this->job('returned');
        MR::whereKey($first->id)->update(['status_updated_at' => now()->subDays(2), 'sla_due_date' => $due, 'response_due_date' => $respond, 'paused_duration_minutes' => 0, 'acknowledged_at' => null]);
        $this->actingAs($this->reporter)->post(route('maintenance.requests.resubmit', $first));
        $first->refresh();
        $this->assertEqualsWithDelta(2 * 24 * 60, $first->paused_duration_minutes, 2);
        $this->assertEqualsWithDelta($due->copy()->addDays(2)->timestamp, $first->sla_due_date->timestamp, 5);
        $this->assertEqualsWithDelta($respond->copy()->addDays(2)->timestamp, $first->response_due_date->timestamp, 5);

        // already acknowledged: the response was given, only the repair deadline moves
        $second = $this->job('returned', ['acknowledged_at' => now()->subDay()]);
        MR::whereKey($second->id)->update(['status_updated_at' => now()->subDay(), 'sla_due_date' => $due, 'response_due_date' => $respond]);
        $this->actingAs($this->reporter)->post(route('maintenance.requests.resubmit', $second));
        $second->refresh();
        $this->assertEqualsWithDelta($due->copy()->addDay()->timestamp, $second->sla_due_date->timestamp, 5);
        $this->assertEqualsWithDelta($respond->timestamp, $second->response_due_date->timestamp, 5);
    }

    public function test_the_app_can_send_back_with_a_note_and_the_reporter_can_send_it_again(): void
    {
        $job = $this->job('acknowledged');

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/repair-requests/{$job->id}/transition", ['status' => 'returned'])->assertStatus(409);
        $this->postJson("/api/repair-requests/{$job->id}/transition", ['status' => 'returned', 'note' => 'ต้องแนบรูปอาการ'])->assertOk();
        $this->assertSame('returned', $job->fresh()->status);

        Sanctum::actingAs($this->stranger);
        $this->postJson("/api/repair-requests/{$job->id}/resubmit")->assertForbidden();

        Sanctum::actingAs($this->reporter);
        $this->postJson("/api/repair-requests/{$job->id}/transition", ['status' => 'pending'])->assertForbidden(); // the reporter's way is /resubmit
        $this->postJson("/api/repair-requests/{$job->id}/resubmit")->assertOk();
        $this->assertSame('pending', $job->fresh()->status);
    }

    public function test_every_page_that_lists_or_shows_a_request_knows_the_new_status(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'SB-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null]);
        $job = $this->job('returned', ['asset_id' => $asset->id]);
        MR::whereKey($job->id)->update(['status_updated_at' => now()]);
        $this->sendBackLog($job, 'ต้องระบุห้องที่เครื่องอยู่');

        $label = 'ส่งกลับแก้ไข';
        foreach ([$this->reporter, $this->staff, $this->admin] as $who) {
            $this->actingAs($who)->get(route('maintenance.requests.show', $job))->assertOk()->assertSee($label);
            $this->actingAs($who)->get(route('maintenance.requests.index'))->assertOk()->assertSee($label);
        }
        $this->actingAs($this->staff)->get(route('repairs.my_jobs'))->assertOk()->assertSee($label);
        $this->actingAs($this->staff)->get(route('assets.show', $asset))->assertOk()->assertSee($label);
        $this->actingAs($this->admin)->get(route('repair.dashboard'))->assertOk();
        $this->actingAs($this->admin)->get(route('maintenance.requests.work-order', $job))->assertOk();
    }

    public function test_the_reporter_sees_why_and_a_button_to_send_it_again_and_the_staff_see_the_send_back_button(): void
    {
        $pending = $this->job('pending');
        $this->actingAs($this->staff)->get(route('maintenance.requests.show', $pending))->assertOk()
            ->assertSee('id="openSendBackModalBtn"', false)->assertSee('id="openRejectModalBtn"', false);
        $this->actingAs($this->reporter)->get(route('maintenance.requests.show', $pending))->assertOk()->assertDontSee('id="openSendBackModalBtn"', false);

        $this->sendBack($pending, $this->staff, 'ต้องระบุเลขครุภัณฑ์');
        $page = $this->actingAs($this->reporter)->get(route('maintenance.requests.show', $pending))->assertOk();
        $page->assertSee('ต้องระบุเลขครุภัณฑ์')->assertSee(route('maintenance.requests.resubmit', $pending), false);
        $this->actingAs($this->stranger)->get(route('maintenance.requests.show', $pending))->assertRedirect();
    }

    private function sendBackLog(MR $job, string $note): void
    {
        DB::table('maintenance_logs')->insert([
            'request_id' => $job->id, 'user_id' => $this->staff->id, 'action' => 'transition', 'from_status' => 'pending', 'to_status' => 'returned',
            'note' => "[รอดำเนินการ -> ส่งกลับแก้ไข] $note", 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
