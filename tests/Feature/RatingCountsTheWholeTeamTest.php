<?php

namespace Tests\Feature;

use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRating;
use App\Models\MaintenanceRequest as MR;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * One rating per request ("how was the work"), and it counts for everybody who was on the request's team, not only the one the system
 * happened to pick. It used to credit one person: the lead, and the team dialog clears every lead, so after any team edit it was
 * whoever had been added last: Ann took the job, did it and resolved it, the supervisor added Bob afterwards, and all the stars were
 * Bob's. The owner chose (2026-11-16) one overall score for the team, counted back through the past ratings too.
 */
class RatingCountsTheWholeTeamTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;
    private User $ann;
    private User $bob;
    private User $cat;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporter = User::factory()->create(['role' => 'member']);
        $this->ann = User::factory()->create(['role' => 'technician', 'name' => 'Ann Alpha']);
        $this->bob = User::factory()->create(['role' => 'it_support', 'name' => 'Bob Bravo']);
        $this->cat = User::factory()->create(['role' => 'network', 'name' => 'Cat Charlie']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** A closed request with the given people on its team (name => assignment status), and the reporter's rating of it. */
    private function ratedJob(array $team, int $score = 5, ?string $comment = null, ?User $representative = null): MR
    {
        $job = MR::factory()->create([
            'status' => 'closed', 'reporter_id' => $this->reporter->id, 'technician_id' => null, 'asset_id' => null,
            'closed_at' => now()->subDay(), 'resolved_at' => now()->subDays(2), 'completed_date' => now()->subDay(),
        ]);
        foreach ($team as $name => $status) {
            MaintenanceAssignment::create([
                'maintenance_request_id' => $job->id, 'user_id' => $this->{$name}->id, 'role' => $this->{$name}->role,
                'is_lead' => false, 'status' => $status,
            ]);
        }
        MaintenanceRating::create([
            'maintenance_request_id' => $job->id, 'rater_id' => $this->reporter->id, 'technician_id' => $representative?->id,
            'score' => $score, 'comment' => $comment,
        ]);

        return $job;
    }

    public function test_every_person_on_the_team_gets_the_score(): void
    {
        $this->ratedJob(['ann' => 'done', 'bob' => 'done'], 4);

        foreach ([$this->ann, $this->bob] as $person) {
            $this->assertSame(1, $person->technicianRatings()->count(), $person->name);
            $this->assertEquals(4, $person->technicianRatings()->avg('score'), $person->name);
        }
        $this->assertSame(0, $this->cat->technicianRatings()->count(), 'somebody who was not on it gets nothing');
    }

    public function test_the_person_who_did_the_work_is_not_beaten_by_whoever_was_added_last(): void
    {
        // the real path: Ann takes the job, does it and resolves it; a supervisor adds Bob afterwards; the reporter rates it
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $job = MR::factory()->create(['status' => 'pending', 'reporter_id' => $this->reporter->id, 'technician_id' => null, 'asset_id' => null]);
        // the factory invents stage dates; a fresh request has none
        MR::whereKey($job->id)->update(['acknowledged_at' => null, 'accepted_at' => null, 'started_at' => null, 'resolved_at' => null, 'closed_at' => null, 'completed_date' => null]);

        $this->actingAs($this->ann)->post(route('maintenance.requests.acknowledge', $job));
        $this->actingAs($this->ann)->post(route('maintenance.requests.accept', $job));
        $this->actingAs($this->ann)->post(route('maintenance.requests.start', $job));
        $this->travel(1)->hours();
        $this->actingAs($supervisor)->post(route('maintenance.requests.assignments.store', $job), ['user_ids' => [$this->ann->id, $this->bob->id]]);
        $this->actingAs($this->ann)->post(route('maintenance.requests.resolve', $job), ['resolution_note' => 'done']);
        $this->travel(10)->seconds();
        $this->actingAs($this->reporter)->post(route('maintenance.requests.close', $job));   // the reporter approves, and only then rates
        $this->assertSame('closed', $job->fresh()->status);

        Sanctum::actingAs($this->reporter);
        $this->postJson("/api/repair-requests/{$job->id}/rating", ['score' => 5])->assertCreated();

        $this->assertSame(1, $this->ann->technicianRatings()->count(), 'Ann did the work');
        $this->assertSame(1, $this->bob->technicianRatings()->count(), 'Bob was on the team');
        $this->assertSame($this->ann->id, MaintenanceRating::where('maintenance_request_id', $job->id)->value('technician_id'),
            'the stored person is the one who pressed "ซ่อมเสร็จ", the one the API has always shown');
    }

    public function test_a_person_taken_off_the_job_is_not_credited(): void
    {
        $this->ratedJob(['ann' => 'done', 'bob' => 'done', 'cat' => 'cancelled']);

        $this->assertSame(1, $this->ann->technicianRatings()->count());
        $this->assertSame(1, $this->bob->technicianRatings()->count());
        $this->assertSame(0, $this->cat->technicianRatings()->count());
    }

    public function test_a_deleted_rating_counts_for_nobody(): void
    {
        $job = $this->ratedJob(['ann' => 'done', 'bob' => 'done']);
        MaintenanceRating::where('maintenance_request_id', $job->id)->first()->delete();

        $this->assertSame(0, $this->ann->technicianRatings()->count());
        $this->assertSame(0, $this->bob->technicianRatings()->count());
    }

    public function test_the_board_counts_each_rating_once_in_its_header_and_gives_each_person_the_score(): void
    {
        $this->ratedJob(['ann' => 'done', 'bob' => 'done', 'cat' => 'done'], 5);
        $this->ratedJob(['ann' => 'done'], 3);

        $page = $this->actingAs($this->admin)->get(route('maintenance.requests.rating.technicians'))->assertOk();

        $this->assertSame(2, (int) $page->viewData('totalReviews'), 'two ratings, not five');
        $byName = collect($page->viewData('technicians')->items())->keyBy('name');
        $this->assertSame(2, (int) $byName['Ann Alpha']->technician_ratings_count);
        $this->assertEquals(4.0, round((float) $byName['Ann Alpha']->technician_ratings_avg_score, 2));
        $this->assertSame(1, (int) $byName['Bob Bravo']->technician_ratings_count);
        $this->assertEquals(5.0, round((float) $byName['Bob Bravo']->technician_ratings_avg_score, 2));
    }

    public function test_a_persons_own_page_and_its_json_work_through_the_team(): void
    {
        $this->ratedJob(['ann' => 'done', 'bob' => 'done'], 5, 'ทำงานดีมาก ทั้งสองคน');
        $this->ratedJob(['bob' => 'done'], 2, 'ช้าไปหน่อย');

        $this->actingAs($this->admin)->get(route('technicians.rating.summary', $this->bob))->assertOk()
            ->assertSee('ทำงานดีมาก ทั้งสองคน')->assertSee('ช้าไปหน่อย');

        $json = $this->actingAs($this->admin)->getJson(route('technicians.rating.summary', $this->bob))->assertOk()->json();
        $this->assertSame(2, $json['total_count']);
        $this->assertEquals(3.5, $json['avg_score']);
        $this->assertCount(2, $json['reviews']);

        $this->actingAs($this->admin)->get(route('technicians.rating.summary', $this->ann))->assertOk()
            ->assertSee('ทำงานดีมาก ทั้งสองคน')->assertDontSee('ช้าไปหน่อย');
    }

    public function test_the_reporters_list_shows_the_whole_team_and_finds_a_job_by_any_of_them(): void
    {
        $job = MR::factory()->create([
            'status' => 'closed', 'reporter_id' => $this->reporter->id, 'technician_id' => $this->ann->id, 'asset_id' => null,
            'closed_at' => now()->subDay(), 'resolved_at' => now()->subDays(2), 'completed_date' => now()->subDay(), 'title' => 'ตู้เย็นเสีย',
        ]);
        foreach (['ann' => 'done', 'bob' => 'done'] as $name => $status) {
            MaintenanceAssignment::create(['maintenance_request_id' => $job->id, 'user_id' => $this->{$name}->id, 'role' => $this->{$name}->role, 'is_lead' => false, 'status' => $status]);
        }

        $list = $this->actingAs($this->reporter)->get(route('maintenance.requests.rating.evaluate'))->assertOk();
        $list->assertSee('Ann Alpha')->assertSee('Bob Bravo');

        // Bob is not the one in charge, and his name still finds the job
        $this->actingAs($this->reporter)->get(route('maintenance.requests.rating.evaluate', ['q' => 'Bravo']))->assertOk()->assertSee('ตู้เย็นเสีย');
        $this->actingAs($this->reporter)->get(route('maintenance.requests.rating.evaluate', ['q' => 'Charlie']))->assertOk()->assertDontSee('ตู้เย็นเสีย');
    }

    public function test_the_rating_form_names_the_team_and_says_the_score_counts_for_all(): void
    {
        $job = MR::factory()->create(['status' => 'closed', 'reporter_id' => $this->reporter->id, 'technician_id' => null, 'asset_id' => null,
            'closed_at' => now()->subDay(), 'resolved_at' => now()->subDays(2), 'completed_date' => now()->subDay()]);
        foreach (['ann' => 'done', 'bob' => 'done', 'cat' => 'cancelled'] as $name => $status) {
            MaintenanceAssignment::create(['maintenance_request_id' => $job->id, 'user_id' => $this->{$name}->id, 'role' => $this->{$name}->role, 'is_lead' => false, 'status' => $status]);
        }

        $page = $this->actingAs($this->reporter)->get(route('maintenance.requests.show', $job))->assertOk();

        $page->assertSee('id="rating-team"', false)->assertSee('คะแนนนี้นับให้ผู้ปฏิบัติงานทุกคน')
            ->assertSee('Ann Alpha')->assertSee('Bob Bravo');
        $this->assertStringNotContainsString('Cat Charlie', (string) $this->extractRatingTeam($page->getContent()), 'somebody taken off the job is not named');
    }

    /** The text inside the rating form's team line. */
    private function extractRatingTeam(string $html): ?string
    {
        return preg_match('/id="rating-team"[^>]*>(.*?)<\/div>/s', $html, $m) ? $m[1] : null;
    }
}
