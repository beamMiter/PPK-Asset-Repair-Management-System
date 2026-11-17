<?php

namespace Tests\Feature;

use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRating;
use App\Models\MaintenanceRequest as MR;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The manual and the web pages say: the reporter approves the finished repair ("อนุมัติปิดงาน") and may then rate it. The app's API let
 * them rate a request that was still "ซ่อมเสร็จ" and closed it for them, so on that road a rating was also the approval, and the web
 * rating lists and the API's "waiting to be rated" list disagreed about what was waiting. Decided 2026-11-17: a rating comes after the
 * approval, on the web and in the app alike, and a rating never closes anything.
 */
class RatingOnlyAfterApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporter = User::factory()->create(['role' => 'member']);
        $this->staff = User::factory()->create(['role' => 'it_support']);
    }

    /** A request in this status with the staff member on its team; its stage dates are the factory's inventions, so set the ones that matter. */
    private function job(string $status): MR
    {
        $job = MR::factory()->create([
            'status' => $status, 'reporter_id' => $this->reporter->id, 'technician_id' => null, 'asset_id' => null,
            'resolved_at' => now()->subDays(2), 'closed_at' => $status === 'closed' ? now()->subDay() : null,
            'completed_date' => $status === 'closed' ? now()->subDay() : null,
        ]);
        MaintenanceAssignment::create(['maintenance_request_id' => $job->id, 'user_id' => $this->staff->id, 'role' => 'it_support', 'is_lead' => false,
            'status' => $status === 'closed' ? 'done' : 'in_progress']);

        return $job;
    }

    private function transitionsOf(MR $job): int
    {
        return DB::table('maintenance_logs')->where('request_id', $job->id)->where('action', 'transition')->count();
    }

    public function test_the_web_refuses_a_rating_until_the_reporter_has_approved_the_repair(): void
    {
        $job = $this->job('resolved');

        $this->actingAs($this->reporter)->post(route('maintenance.requests.rating.store', $job), ['score' => 5])->assertForbidden();
        $this->actingAs($this->reporter)->get(route('maintenance.requests.rating.create', $job))->assertForbidden();

        $this->assertSame(0, MaintenanceRating::where('maintenance_request_id', $job->id)->count());
        $this->assertSame('resolved', $job->fresh()->status, 'and it stays as it was');
    }

    public function test_the_app_refuses_it_too_with_a_message_that_says_what_to_do(): void
    {
        $job = $this->job('resolved');

        Sanctum::actingAs($this->reporter);
        $res = $this->postJson("/api/repair-requests/{$job->id}/rating", ['score' => 5])->assertStatus(422);

        $this->assertStringContainsString('อนุมัติ', $res->json('message'));
        $this->assertSame(0, MaintenanceRating::where('maintenance_request_id', $job->id)->count());
        $this->assertSame('resolved', $job->fresh()->status);
    }

    public function test_after_the_approval_both_accept_it_and_a_rating_closes_nothing(): void
    {
        $webJob = $this->job('closed');
        $appJob = $this->job('closed');
        $before = [$this->transitionsOf($webJob), $this->transitionsOf($appJob)];

        $this->actingAs($this->reporter)->post(route('maintenance.requests.rating.store', $webJob), ['score' => 4]);
        Sanctum::actingAs($this->reporter);
        $this->postJson("/api/repair-requests/{$appJob->id}/rating", ['score' => 5])->assertCreated();

        $this->assertSame(1, MaintenanceRating::where('maintenance_request_id', $webJob->id)->count());
        $this->assertSame(1, MaintenanceRating::where('maintenance_request_id', $appJob->id)->count());
        $this->assertSame($before, [$this->transitionsOf($webJob), $this->transitionsOf($appJob)], 'no transition was written by rating');
        $this->assertFalse(DB::table('maintenance_logs')->where('note', 'like', '%ปิดงานอัตโนมัติ%')->exists());
    }

    public function test_the_apps_waiting_to_be_rated_list_has_only_what_can_be_rated(): void
    {
        $resolved = $this->job('resolved');
        $closed = $this->job('closed');

        Sanctum::actingAs($this->reporter);
        $ids = collect($this->getJson('/api/repair-requests/pending/evaluations')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$closed->id], $ids, 'the web list holds closed requests only; the app\'s list now agrees');
        $this->assertNotContains($resolved->id, $ids);
    }

    public function test_a_resolved_request_is_approved_with_the_approve_button_and_then_rated(): void
    {
        $job = $this->job('resolved');

        $this->actingAs($this->reporter)->post(route('maintenance.requests.close', $job));
        $this->assertSame('closed', $job->fresh()->status);

        Sanctum::actingAs($this->reporter);
        $this->postJson("/api/repair-requests/{$job->id}/rating", ['score' => 5])->assertCreated();
    }
}
