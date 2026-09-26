<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The SLA clock. The deadline used to be worked out once, when the request was made: a request made without a type had none for
 * good (choosing the type afterwards did nothing, so the SLA page never saw it), and one moved to another type kept the old
 * type's. And a job on hold — whose clock is stopped — was listed as overdue as soon as its raw deadline passed.
 */
class RequestSlaClockTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '2026-03-01 08:00:00';

    private function type(string $name, ?int $response, ?int $resolution): MaintenanceRequestType
    {
        return MaintenanceRequestType::create([
            'name' => $name, 'is_active' => true, 'sort_order' => 1,
            'default_response_minutes' => $response, 'default_resolution_minutes' => $resolution,
        ]);
    }

    private function job(string $status = 'in_progress', array $extra = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($extra + [
            'status' => $status,
            'reporter_id' => User::factory()->create(['role' => 'member'])->id,
            'technician_id' => null,
            'type_id' => null,
            'request_date' => self::BASE,
            'sla_due_date' => null,
            'response_due_date' => null,
            'paused_duration_minutes' => 0,
            'on_hold_at' => null,
            'acknowledged_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'asset_id' => Asset::factory()->create(['asset_code' => 'K-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null])->id,
        ]);
    }

    private function setType(MaintenanceRequest $req, ?MaintenanceRequestType $type): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('maintenance.requests.type.update', $req), ['type_id' => $type?->id]);
    }

    private function at(?Carbon $t): ?string
    {
        return $t?->format('Y-m-d H:i:s');
    }

    public function test_a_type_chosen_after_the_request_was_made_sets_its_deadlines(): void
    {
        $type = $this->type('Network', 30, 120);
        $req = $this->job('pending');
        $this->assertNull($req->sla_due_date);

        $this->setType($req, $type);

        $fresh = $req->fresh();
        $this->assertSame('2026-03-01 10:00:00', $this->at($fresh->sla_due_date), 'request date + 120 minutes');
        $this->assertSame('2026-03-01 08:30:00', $this->at($fresh->response_due_date), 'request date + 30 minutes');
    }

    public function test_moving_a_request_to_another_type_recomputes_the_deadline(): void
    {
        $quick = $this->type('Quick', 30, 120);
        $slow = $this->type('Slow', 60, 480);
        $req = $this->job('in_progress', ['type_id' => $quick->id]);
        $this->assertSame('2026-03-01 10:00:00', $this->at($req->fresh()->sla_due_date), 'set when it was made');

        $this->setType($req, $slow);

        $this->assertSame('2026-03-01 16:00:00', $this->at($req->fresh()->sla_due_date));
    }

    public function test_time_already_spent_on_hold_is_kept_in_the_new_deadline(): void
    {
        $type = $this->type('Network', 30, 120);
        $req = $this->job('in_progress', ['paused_duration_minutes' => 45]);

        $this->setType($req, $type);

        $this->assertSame('2026-03-01 10:45:00', $this->at($req->fresh()->sla_due_date), '120 minutes + 45 on hold');
    }

    public function test_a_finished_job_keeps_the_deadline_it_had(): void
    {
        $type = $this->type('Network', 30, 120);
        foreach (['resolved', 'closed', 'cancelled', 'rejected'] as $status) {
            $req = $this->job($status, ['sla_due_date' => '2026-03-05 12:00:00']);

            $this->setType($req, $type);

            $this->assertSame('2026-03-05 12:00:00', $this->at($req->fresh()->sla_due_date), "$status is history");
        }
    }

    public function test_a_type_without_minutes_or_none_at_all_leaves_the_deadline_alone(): void
    {
        $plain = $this->type('Plain', null, null);
        $req = $this->job('in_progress', ['sla_due_date' => '2026-03-05 12:00:00']);

        $this->setType($req, $plain);
        $this->assertSame('2026-03-05 12:00:00', $this->at($req->fresh()->sla_due_date));

        $this->setType($req, null);
        $this->assertSame('2026-03-05 12:00:00', $this->at($req->fresh()->sla_due_date));
    }

    public function test_the_response_deadline_is_not_moved_once_the_job_was_acknowledged(): void
    {
        $type = $this->type('Network', 30, 120);
        $req = $this->job('in_progress', ['acknowledged_at' => '2026-03-01 08:10:00', 'response_due_date' => '2026-03-01 09:00:00']);

        $this->setType($req, $type);

        $fresh = $req->fresh();
        $this->assertSame('2026-03-01 09:00:00', $this->at($fresh->response_due_date), 'the response was answered; that deadline is history');
        $this->assertSame('2026-03-01 10:00:00', $this->at($fresh->sla_due_date));
    }

    public function test_the_deadline_stands_still_only_while_the_job_is_not_on_hold(): void
    {
        $now = Carbon::parse('2026-03-02 12:00:00');
        $due = Carbon::parse('2026-03-02 10:00:00');

        $working = new MaintenanceRequest(['status' => 'in_progress', 'sla_due_date' => $due]);
        $this->assertSame($this->at($due), $this->at($working->slaDeadline($now)));

        $held = new MaintenanceRequest(['status' => 'on_hold', 'sla_due_date' => $due, 'on_hold_at' => $now->copy()->subHours(3)]);
        $this->assertSame('2026-03-02 13:00:00', $this->at($held->slaDeadline($now)), 'three hours on hold push the deadline out by three hours');

        $this->assertNull((new MaintenanceRequest(['status' => 'on_hold']))->slaDeadline($now));
    }

    public function test_a_job_on_hold_is_overdue_only_if_it_was_already_late_when_it_was_put_on_hold(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // put on hold at 3 h ago, deadline 1 h ago: the clock stopped 2 h before the deadline — not late
        $early = $this->job('on_hold', ['on_hold_at' => now()->subHours(3), 'sla_due_date' => now()->subHour()]);
        // put on hold 1 h ago, deadline was 3 h ago: it was already late
        $late = $this->job('on_hold', ['on_hold_at' => now()->subHour(), 'sla_due_date' => now()->subHours(3)]);
        $working = $this->job('in_progress', ['sla_due_date' => now()->subHours(2)]);

        $data = $this->actingAs($admin)->get(route('maintenance.sla.index'))->assertOk();
        $overdue = collect($data->viewData('breachedTickets'))->pluck('id');

        $this->assertFalse($overdue->contains($early->id), 'clock stopped before the deadline');
        $this->assertTrue($overdue->contains($late->id), 'it was late before it was put on hold');
        $this->assertTrue($overdue->contains($working->id));
        $this->assertSame(2, $data->viewData('dashboard')['breached_count'], 'the KPI counts the same jobs as the list');
    }

    public function test_the_job_page_does_not_call_a_paused_or_finished_job_overdue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $held = $this->job('on_hold', ['on_hold_at' => now()->subHours(3), 'sla_due_date' => now()->subHour()]);
        $cancelled = $this->job('cancelled', ['sla_due_date' => now()->subDays(2)]);
        $late = $this->job('in_progress', ['sla_due_date' => now()->subHours(2)]);

        $this->actingAs($admin)->get(route('maintenance.requests.show', $held))->assertOk()->assertDontSee('เกินกำหนด SLA');
        $this->actingAs($admin)->get(route('maintenance.requests.show', $cancelled))->assertOk()->assertDontSee('เกินกำหนด SLA');
        $this->actingAs($admin)->get(route('maintenance.requests.show', $late))->assertOk()->assertSee('เกินกำหนด SLA');
    }
}
