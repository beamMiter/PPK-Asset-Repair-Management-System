<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest as MR;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A reporter could not take back their own request until the staff had accepted it: a request filed by mistake, or twice, had to wait
 * for somebody to reject it, which is the junk the owner wants less of. They may withdraw it (ยกเลิก) at any moment before the work is
 * finished now: while it waits (pending), once it is acknowledged, and (as before) when it is accepted, in progress, on hold or sent back.
 * Decided 2026-11-17.
 */
class ReporterCanWithdrawTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;
    private User $stranger;
    private User $staff;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporter = User::factory()->create(['role' => 'member']);
        $this->stranger = User::factory()->create(['role' => 'member']);
        $this->staff = User::factory()->create(['role' => 'it_support']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function job(string $status, array $overrides = []): MR
    {
        return MR::factory()->create(array_merge(['status' => $status, 'reporter_id' => $this->reporter->id, 'technician_id' => null, 'asset_id' => null], $overrides));
    }

    private function withdraw(MR $job, User $by, string $reason = 'แจ้งผิดห้อง')
    {
        return $this->actingAs($by)->post(route('maintenance.requests.cancel', $job), ['cancel_reason' => $reason]);
    }

    public function test_the_reporter_withdraws_a_request_nobody_has_taken_on_yet(): void
    {
        foreach (['pending', 'acknowledged'] as $status) {
            $job = $this->job($status);

            $this->withdraw($job, $this->reporter, 'แจ้งซ้ำกับใบก่อนหน้า');

            $this->assertSame('cancelled', $job->fresh()->status, $status);
            $log = DB::table('maintenance_logs')->where('request_id', $job->id)->where('to_status', 'cancelled')->first();
            $this->assertNotNull($log, "$status: the move is in the history");
            $this->assertStringContainsString('แจ้งซ้ำกับใบก่อนหน้า', $log->note);
        }
    }

    public function test_withdrawing_frees_the_asset_and_clears_a_team_somebody_had_been_given(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'WD-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null]);
        $job = $this->job('acknowledged', ['asset_id' => $asset->id]);
        MaintenanceAssignment::create(['maintenance_request_id' => $job->id, 'user_id' => $this->staff->id, 'role' => 'it_support', 'is_lead' => false, 'status' => 'in_progress']);
        $this->assertSame('in_repair', $asset->fresh()->status);

        $this->withdraw($job, $this->reporter);

        $this->assertSame('cancelled', $job->fresh()->status);
        $this->assertSame('active', $asset->fresh()->status, 'a request that is over frees its asset');
        $this->assertSame('cancelled', MaintenanceAssignment::where('maintenance_request_id', $job->id)->value('status'));
    }

    public function test_it_is_still_the_reporters_alone_to_withdraw_and_the_staff_have_their_own_buttons(): void
    {
        $job = $this->job('pending');

        $this->withdraw($job, $this->stranger)->assertRedirect();
        $this->assertSame('pending', $job->fresh()->status, 'another member cannot');

        // a staff member turns a pending request away with reject / send back, not with the reporter's withdrawal
        $this->withdraw($job, $this->staff)->assertRedirect();
        $this->assertSame('pending', $job->fresh()->status);

        $this->withdraw($job, $this->admin);
        $this->assertSame('cancelled', $job->fresh()->status, 'an admin may, for the reporter');
    }

    public function test_nothing_that_is_over_or_finished_can_be_withdrawn(): void
    {
        foreach (['resolved', 'closed', 'cancelled', 'rejected'] as $status) {
            $job = $this->job($status);
            $this->withdraw($job, $this->reporter);
            $this->assertSame($status, $job->fresh()->status, $status);
        }
    }

    public function test_what_could_be_withdrawn_before_still_can(): void
    {
        foreach (['accepted', 'in_progress', 'on_hold', 'returned'] as $status) {
            $job = $this->job($status);
            $this->withdraw($job, $this->reporter);
            $this->assertSame('cancelled', $job->fresh()->status, $status);
        }
    }

    public function test_the_app_withdraws_with_its_own_call_and_an_edit_cannot_pretend_to(): void
    {
        $job = $this->job('pending');

        Sanctum::actingAs($this->stranger);
        $this->postJson("/api/repair-requests/{$job->id}/cancel", ['cancel_reason' => 'x'])->assertForbidden();
        $this->assertSame('pending', $job->fresh()->status);

        Sanctum::actingAs($this->reporter);
        // an edit that names a status used to answer 200 and change nothing (the service ignored it for a reporter); it is refused now
        $this->putJson("/api/repair-requests/{$job->id}", ['status' => 'cancelled', 'note' => 'แจ้งผิด'])->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertSame('pending', $job->fresh()->status);

        $this->postJson("/api/repair-requests/{$job->id}/cancel", ['cancel_reason' => 'แจ้งผิด'])->assertOk();
        $this->assertSame('cancelled', $job->fresh()->status);

        $done = $this->job('resolved');
        $this->postJson("/api/repair-requests/{$done->id}/cancel")->assertForbidden();
        $this->assertSame('resolved', $done->fresh()->status);
    }

    public function test_the_job_page_offers_the_withdraw_button_to_the_reporter_while_it_waits(): void
    {
        foreach (['pending', 'acknowledged'] as $status) {
            $job = $this->job($status);

            $this->actingAs($this->reporter)->get(route('maintenance.requests.show', $job))->assertOk()
                ->assertSee('id="openCancelModalBtn"', false)->assertSee('ยกเลิกการซ่อมบำรุง');
            $this->actingAs($this->staff)->get(route('maintenance.requests.show', $job))->assertOk()->assertDontSee('id="openCancelModalBtn"', false);
        }

        $done = $this->job('resolved');
        $this->actingAs($this->reporter)->get(route('maintenance.requests.show', $done))->assertOk()->assertDontSee('id="openCancelModalBtn"', false);
    }
}
