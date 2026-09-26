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
 * The reporter's waiting list, their history and the rate button counted a rating by ANYONE, while the guard and the unique key count one per
 * person: an admin's rating hid the job from the reporter, showed the admin's stars in the reporter's history, and the reporter could still
 * rate it by URL. Each person's own rating now decides what is theirs.
 */
class RatingIsPerRaterTest extends TestCase
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

    private function closedJobWithTeam(): MaintenanceRequest
    {
        $req = $this->job('closed', ['technician_id' => $this->tech->id, 'closed_at' => now()->subDays(2)]);
        MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $this->tech->id, 'status' => 'done', 'is_lead' => true]);

        return $req;
    }

    /** A rating by somebody who is not the reporter, as the data of before the rule can hold. */
    private function oldRatingBy(User $rater, MaintenanceRequest $req, int $score, ?string $comment = null): void
    {
        MaintenanceRating::create(['maintenance_request_id' => $req->id, 'rater_id' => $rater->id, 'technician_id' => $this->tech->id, 'score' => $score, 'comment' => $comment]);
    }

    public function test_an_admins_rating_does_not_take_the_job_off_the_reporters_waiting_list(): void
    {
        $req = $this->closedJobWithTeam();
        $this->oldRatingBy($this->admin, $req, 4);
        $this->assertSame(1, MaintenanceRating::where('maintenance_request_id', $req->id)->count());

        $page = $this->actingAs($this->member)->get(route('maintenance.requests.rating.evaluate'))->assertOk();

        $this->assertSame([$req->id], $page->viewData('requests')->pluck('id')->all(), 'still waiting for the reporter\'s own');
        $this->assertSame(1, $page->viewData('pendingCount'));
        $this->assertSame(0, $page->viewData('totalRatedCount'), 'and the admin\'s stars are not the reporter\'s history');
    }

    public function test_the_history_shows_the_reporters_own_rating_not_the_admins(): void
    {
        $req = $this->closedJobWithTeam();
        $this->oldRatingBy($this->admin, $req, 1, 'ความเห็นของแอดมิน');
        $this->actingAs($this->member)->post(route('maintenance.requests.rating.store', $req), ['score' => 5, 'comment' => 'ความเห็นของผู้แจ้ง']);

        $page = $this->actingAs($this->member)->get(route('maintenance.requests.rating.evaluate', ['tab' => 'rated']))->assertOk();

        $row = $page->viewData('requests')->first();
        $this->assertSame(5, $row->rating->score);
        $page->assertSee('ความเห็นของผู้แจ้ง')->assertDontSee('ความเห็นของแอดมิน');
        $this->assertSame(0, $page->viewData('pendingCount'), 'their own rating takes it off their list');
    }

    public function test_the_rate_button_is_the_reporters_until_they_have_rated_themselves(): void
    {
        $req = $this->closedJobWithTeam();
        $this->oldRatingBy($this->admin, $req, 4);

        $this->assertTrue($this->member->can('rate', $req->fresh()), 'the admin\'s rating did not use up the reporter\'s');

        $this->actingAs($this->member)->post(route('maintenance.requests.rating.store', $req), ['score' => 5]);

        $this->assertFalse($this->member->can('rate', $req->fresh()), 'after their own it is used');
    }
}
