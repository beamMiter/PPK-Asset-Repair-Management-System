<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\MaintenanceTransitionService;
use App\Traits\ApiResponseWithToast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/**
 * The small things around the buttons: what a refusal says and when it is given, which team rows a finished job leaves behind,
 * and a double click.
 */
class RequestLifecycleHygieneTest extends TestCase
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
            'paused_duration_minutes' => 0,
            'on_hold_at' => null,
            'asset_id' => Asset::factory()->create(['asset_code' => 'H-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null])->id,
        ]);
    }

    private function onTeam(MaintenanceRequest $req, User ...$users): void
    {
        foreach ($users as $u) {
            MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $u->id, 'status' => 'in_progress', 'is_lead' => false]);
        }
    }

    /** @return list<string> */
    private function teamStatuses(MaintenanceRequest $req): array
    {
        return MaintenanceAssignment::where('maintenance_request_id', $req->id)->orderBy('user_id')->pluck('status')->all();
    }

    // ---- resolve is a step of "in progress" only (the state map says so; the policy used to say "or on hold" too) ----

    public function test_a_job_on_hold_has_to_be_resumed_before_it_can_be_resolved(): void
    {
        $tech = User::factory()->create(['role' => 'it_support']);
        $held = $this->job('on_hold', ['on_hold_at' => now()->subHour()]);
        $this->onTeam($held, $tech);

        $this->assertFalse($tech->can('resolve', $held));
        $this->actingAs($tech)->postJson("/api/repair-requests/{$held->id}/transition", ['status' => 'resolved', 'note' => 'x'])->assertForbidden();
        $this->assertSame('on_hold', $held->fresh()->status);
    }

    // ---- the team a finished job leaves behind ----

    public function test_finishing_a_job_settles_its_team_even_when_nobody_was_the_person_in_charge(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = User::factory()->create(['role' => 'it_support']);
        $b = User::factory()->create(['role' => 'network']);

        $done = $this->job('in_progress', ['started_at' => now()]);
        $this->onTeam($done, $a, $b);   // put there by the assign dialog: no technician_id
        $this->actingAs($admin)->post(route('maintenance.requests.resolve', $done), ['resolution_note' => 'fixed']);
        $this->assertSame(['done', 'done'], $this->teamStatuses($done), 'the work order shows them as done');

        $dropped = $this->job('in_progress', ['started_at' => now()]);
        $this->onTeam($dropped, $a, $b);
        $this->actingAs($admin)->post(route('maintenance.requests.cancel', $dropped), ['cancel_reason' => 'duplicate']);
        $this->assertSame(['cancelled', 'cancelled'], $this->teamStatuses($dropped));
    }

    public function test_a_technician_who_cancels_his_job_lands_on_its_page(): void
    {
        $tech = User::factory()->create(['role' => 'it_support']);
        $req = $this->job('in_progress', ['started_at' => now()]);
        $this->onTeam($req, $tech);

        $this->actingAs($tech)->followingRedirects()->post(route('maintenance.requests.cancel', $req), ['cancel_reason' => 'duplicate'])
            ->assertOk()
            ->assertSee($req->request_no);   // the job page — not the dashboard with a "no permission" toast

        $this->assertSame('cancelled', $req->fresh()->status);
    }

    // ---- refusals ----

    public function test_an_outsider_is_refused_before_the_body_is_looked_at(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $pending = $this->job('pending');
        $working = $this->job('in_progress');

        $long = str_repeat('x', 3000);   // invalid on purpose: a 422 would tell an outsider what a valid request looks like

        $this->actingAs($member)->postJson(route('maintenance.requests.reject', $pending), ['reject_reason' => $long])->assertForbidden();
        $this->actingAs($member)->postJson(route('maintenance.requests.cancel', $pending), ['cancel_reason' => $long])->assertForbidden();
        $this->actingAs($member)->postJson(route('maintenance.requests.hold', $working), [])->assertForbidden();
        $this->actingAs($member)->postJson(route('maintenance.requests.resolve', $working), [])->assertForbidden();
    }

    public function test_an_unexpected_failure_does_not_show_its_internals_to_the_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $req = $this->job('accepted');

        $this->mock(MaintenanceTransitionService::class, function ($mock) {
            $mock->shouldReceive('applyTransition')->andThrow(new \RuntimeException('SQLSTATE[23000]: Integrity constraint violation: secret_table'));
        });

        $res = $this->actingAs($admin)->postJson(route('maintenance.requests.start', $req));

        $res->assertStatus(500);
        $this->assertStringNotContainsString('SQLSTATE', $res->getContent());
        $this->assertStringNotContainsString('secret_table', $res->getContent());
    }

    public function test_only_our_own_refusals_are_shown_as_they_are(): void
    {
        $say = new class
        {
            use ApiResponseWithToast;

            public function of(\Throwable $e): string
            {
                return $this->friendlyMessage($e);
            }
        };

        $this->assertSame('สถานะไม่ถูกต้อง', $say->of(new ConflictHttpException('สถานะไม่ถูกต้อง')));
        $this->assertSame('ไม่สามารถแจ้งซ่อมทรัพย์สินที่จำหน่ายออกแล้วได้', $say->of(new \Exception('ไม่สามารถแจ้งซ่อมทรัพย์สินที่จำหน่ายออกแล้วได้', 101)));
        $this->assertSame('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง', $say->of(new \RuntimeException('SQLSTATE[HY000] boom')));
    }

    // ---- a double click ----

    public function test_a_second_identical_move_is_refused_and_does_not_write_a_history_row(): void
    {
        $tech = User::factory()->create(['role' => 'it_support']);
        $req = $this->job('accepted', ['accepted_at' => now()]);
        $service = app(MaintenanceTransitionService::class);

        $service->applyTransition($req->fresh(), ['status' => 'in_progress'], $tech->id);

        try {
            $service->applyTransition($req->fresh(), ['status' => 'in_progress'], $tech->id);   // the second click, past the gate
            $this->fail('the second move should be refused');
        } catch (ConflictHttpException|\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->assertSame(0, MaintenanceLog::where('request_id', $req->id)->whereColumn('from_status', 'to_status')->count(), 'no "in progress → in progress" row');
        $this->assertSame(1, MaintenanceLog::where('request_id', $req->id)->where('to_status', 'in_progress')->count());
    }
}
