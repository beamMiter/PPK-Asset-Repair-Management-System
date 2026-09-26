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
 * An edit that did not resend the operation report used to wipe it — the technician's method, remark, property code and flags became
 * empty and carried the caller's name — through the web JSON route and the API alike. The edit form always sends its text fields, so
 * it still writes the whole report; a call that sends none of them leaves the report alone.
 */
class OperationReportKeptOnPartialEditTest extends TestCase
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
            // the factory dates every stage at random (some in the future): a job starts with none, its steps write their own
            'acknowledged_at' => null, 'accepted_at' => null, 'started_at' => null, 'on_hold_at' => null, 'resolved_at' => null,
            'closed_at' => null, 'completed_date' => null, 'paused_duration_minutes' => 0,
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

    private function jobWithReport(): MaintenanceRequest
    {
        $req = $this->job('in_progress', ['technician_id' => $this->tech->id]);
        MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $this->tech->id, 'status' => 'in_progress', 'is_lead' => true]);
        $req->operationLog()->create([
            'operation_date' => '2026-09-20', 'operation_method' => 'requisition', 'property_code' => 'PC-1', 'remark' => 'เปลี่ยนแรม',
            'require_precheck' => true, 'issue_software' => false, 'issue_hardware' => true, 'user_id' => $this->tech->id,
        ]);

        return $req;
    }

    private function report(MaintenanceRequest $req): array
    {
        return $req->fresh()->operationLog->only(['operation_date', 'operation_method', 'property_code', 'remark', 'require_precheck', 'issue_software', 'issue_hardware', 'user_id']);
    }

    public function test_an_edit_that_does_not_send_the_report_leaves_it_alone(): void
    {
        $req = $this->jobWithReport();
        $before = $this->report($req);

        $this->actingAs($this->admin)->putJson(route('maintenance.requests.update', ['req' => $req->id]), ['title' => 'แก้แค่ชื่อเรื่อง'])->assertOk();

        $this->assertSame('แก้แค่ชื่อเรื่อง', $req->fresh()->title);
        $this->assertEquals($before, $this->report($req), 'the technician\'s report, and the name on it, are untouched');
    }

    public function test_the_api_put_does_not_wipe_the_report_either(): void
    {
        $req = $this->jobWithReport();
        $before = $this->report($req);
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/repair-requests/{$req->id}", ['title' => 'ผ่าน API', 'location_text' => 'ห้อง 5'])->assertOk();

        $this->assertEquals($before, $this->report($req));
    }

    public function test_the_edit_form_still_writes_the_whole_report_and_an_unticked_box_is_false(): void
    {
        $req = $this->jobWithReport();

        // the form sends every text field and leaves an unticked checkbox out
        $this->actingAs($this->admin)->putJson(route('maintenance.requests.update', ['req' => $req->id]), [
            'title' => $req->title, 'operation_date' => '2026-09-25', 'operation_method' => 'service_fee', 'property_code' => 'PC-2',
            'remark' => 'ใหม่', 'issue_software' => 1,
        ])->assertOk();

        $report = $this->report($req);
        $this->assertSame('service_fee', $report['operation_method']);
        $this->assertSame('ใหม่', $report['remark']);
        $this->assertTrue((bool) $report['issue_software']);
        $this->assertFalse((bool) $report['issue_hardware'], 'ticked before, unticked now');
        $this->assertFalse((bool) $report['require_precheck']);
        $this->assertSame($this->admin->id, $report['user_id']);
    }

    public function test_a_call_that_sends_only_flags_changes_only_those(): void
    {
        $req = $this->jobWithReport();

        $this->actingAs($this->admin)->putJson(route('maintenance.requests.update', ['req' => $req->id]), ['issue_software' => 1, 'issue_hardware' => 0])->assertOk();

        $report = $this->report($req);
        $this->assertTrue((bool) $report['issue_software']);
        $this->assertFalse((bool) $report['issue_hardware']);
        $this->assertSame('เปลี่ยนแรม', $report['remark'], 'the rest of the report stays');
        $this->assertSame('requisition', $report['operation_method']);
    }

    public function test_a_reporter_editing_their_request_never_touches_a_report(): void
    {
        $pending = $this->job('pending');

        $this->actingAs($this->member)->putJson(route('maintenance.requests.update', ['req' => $pending->id]), ['title' => 'แก้ไข', 'remark' => 'พยายามใส่รายงาน'])->assertOk();

        $this->assertNull($pending->fresh()->operationLog, 'no report is made from a reporter\'s edit');
    }
}
