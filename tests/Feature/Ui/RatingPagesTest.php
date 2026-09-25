<?php

namespace Tests\Feature\Ui;

use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRating;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The three rating pages — the reporter's "ประเมินความพึงพอใจ", the team board and a person's own summary — follow the list-page
 * pattern (same header, tables on a desktop and cards on a phone, the shared buttons) and carry what a reader needs to act on:
 * how long a job has left to be rated, and what a person's numbers are made of.
 */
class RatingPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $tech;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = User::factory()->create(['role' => 'member']);
        $this->tech = User::factory()->create(['role' => 'it_support', 'name' => 'ช่างสมชาย']);
    }

    private function closedDaysAgo(int $days, array $extra = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($extra + [
            'reporter_id' => $this->member->id,
            'status' => MaintenanceRequest::STATUS_CLOSED,
            'closed_at' => now()->subDays($days),
            'technician_id' => $this->tech->id,
        ]);
    }

    private function rate(MaintenanceRequest $req, int $score, ?string $comment = null, ?Carbon $at = null, ?User $rater = null): MaintenanceRating
    {
        $rating = MaintenanceRating::create([
            'maintenance_request_id' => $req->id,
            'rater_id' => ($rater ?? $this->member)->id,
            'technician_id' => $this->tech->id,
            'score' => $score,
            'comment' => $comment,
        ]);
        if ($at) {
            $rating->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        }

        return $rating;
    }

    private function evaluate()
    {
        return $this->actingAs($this->member)->get(route('maintenance.requests.rating.evaluate'))->assertOk();
    }

    // ---- ประเมินความพึงพอใจ ----------------------------------------------------------------------------------------

    public function test_the_job_that_runs_out_of_time_first_comes_first_and_says_how_long_is_left(): void
    {
        $plenty = $this->closedDaysAgo(5);     // 25 days left
        $tight = $this->closedDaysAgo(28);     // 2 days left
        $middle = $this->closedDaysAgo(15);    // 15 days left

        $page = $this->evaluate();

        $this->assertSame([$tight->id, $middle->id, $plenty->id], $page->viewData('pendingRequests')->pluck('id')->all());
        $this->assertSame([2, 15, 25], $page->viewData('pendingRequests')->pluck('rating_days_left')->all());
        $page->assertSee('เหลือ 2 วัน')->assertSee('เหลือ 15 วัน')->assertSee('เหลือ 25 วัน');
    }

    public function test_the_last_day_and_the_day_after_it(): void
    {
        $last = $this->closedDaysAgo(30);
        $gone = $this->closedDaysAgo(31);
        foreach ([$last, $gone] as $req) {   // the guard attributes a rating to a team member of the job
            MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $this->tech->id, 'status' => 'done', 'is_lead' => true]);
        }

        $page = $this->evaluate();

        $ids = $page->viewData('pendingRequests')->pluck('id')->all();
        $this->assertContains($last->id, $ids);
        $this->assertNotContains($gone->id, $ids);
        $page->assertSee('วันสุดท้าย');
        // and the guard agrees: the last day can still be rated, the next one cannot
        $this->actingAs($this->member)->get(route('maintenance.requests.rating.create', $last))->assertRedirect(route('maintenance.requests.show', $last));
        $this->assertNull(session('toast.message'), 'the last day is still allowed');
        $this->flushSession();
        $this->actingAs($this->member)->get(route('maintenance.requests.rating.create', $gone));
        $this->assertSame('เลยระยะเวลาที่สามารถให้คะแนนงานนี้ได้แล้ว', session('toast.message'));
    }

    public function test_a_banner_counts_the_jobs_about_to_run_out_over_the_whole_list(): void
    {
        $this->closedDaysAgo(2);
        $this->closedDaysAgo(24);   // 6 left
        $this->closedDaysAgo(28);   // 2 left
        $this->closedDaysAgo(23);   // 7 left: the edge, still counted

        $page = $this->evaluate();

        $this->assertSame(3, $page->viewData('expiringCount'));
        $page->assertSee('มี 3 งานที่เหลือเวลาประเมินไม่เกิน 7 วัน');
    }

    public function test_no_banner_when_nothing_is_about_to_run_out(): void
    {
        $this->closedDaysAgo(3);

        $this->evaluate()->assertDontSee('เหลือเวลาประเมินไม่เกิน');
    }

    public function test_the_banner_counts_jobs_beyond_the_first_page(): void
    {
        foreach (range(1, 12) as $i) {
            $this->closedDaysAgo(26);
        }

        $page = $this->evaluate();

        $this->assertSame(12, $page->viewData('expiringCount'));
        $this->assertCount(10, $page->viewData('pendingRequests')->items());
    }

    public function test_each_pending_job_shows_who_did_it_where_and_when_it_closed(): void
    {
        $this->closedDaysAgo(4, ['location_text' => 'ห้องฉุกเฉิน ชั้น 2', 'title' => 'เครื่องพิมพ์เสีย']);

        $this->evaluate()
            ->assertSee('เครื่องพิมพ์เสีย')
            ->assertSee('ห้องฉุกเฉิน ชั้น 2')
            ->assertSee('ช่างสมชาย')
            ->assertSee(\App\Support\ThaiDate::short(now()->subDays(4)));
    }

    public function test_the_history_lists_the_newest_rating_first_whatever_the_close_date(): void
    {
        $closedLongAgo = $this->closedDaysAgo(20);
        $closedRecently = $this->closedDaysAgo(2);
        $this->rate($closedLongAgo, 5, 'ล่าสุด', now()->subHour());      // closed earlier but rated last
        $this->rate($closedRecently, 3, 'เก่ากว่า', now()->subDays(5));

        $this->assertSame([$closedLongAgo->id, $closedRecently->id], $this->evaluate()->viewData('ratedRequests')->pluck('id')->all());
    }

    public function test_the_history_shows_the_stars_the_words_and_the_date_of_the_rating(): void
    {
        $req = $this->closedDaysAgo(10);
        $this->rate($req, 4, 'ช่างมาเร็ว', now()->subDays(3));

        $this->evaluate()
            ->assertSee('ช่างมาเร็ว')
            ->assertSee('4.0 · พอใจ')
            ->assertSee(\App\Support\ThaiDate::short(now()->subDays(3)))
            ->assertSee('aria-label="4.0 จาก 5 ดาว"', false);
    }

    public function test_the_stats_are_on_a_phone_too(): void
    {
        $this->closedDaysAgo(3);

        $html = $this->evaluate()->getContent();

        // one copy above the title for a phone, one beside it from md up — before, a phone saw none
        $this->assertSame(2, substr_count($html, 'อัตราประเมิน:'));
        $this->assertSame(2, substr_count($html, 'รอประเมิน:'));
        $this->assertMatchesRegularExpression('/md:hidden flex flex-wrap[^"]*"[^>]*>\s*<div[^>]*>\s*<span[^>]*>อัตราประเมิน:/u', $html);
    }

    public function test_each_list_is_a_table_on_a_desktop_and_cards_on_a_phone(): void
    {
        $this->rate($this->closedDaysAgo(9), 5);
        $this->closedDaysAgo(3);

        $html = $this->evaluate()->getContent();

        $this->assertSame(2, substr_count($html, 'hidden md:block overflow-x-auto'), 'a desktop table for each list');
        $this->assertSame(2, substr_count($html, 'md:hidden grid gap-3'), 'a card list for each list on a phone');
        $this->assertStringNotContainsString('rounded-sm', $html, 'the old square-cornered cards are gone');
    }

    public function test_the_page_links_to_each_list_and_paginates_it_in_place(): void
    {
        foreach (range(1, 11) as $i) {
            $this->closedDaysAgo(3);
        }

        $html = $this->evaluate()->getContent();

        $this->assertStringContainsString('pending_page=2#pending', str_replace('&amp;', '&', $html));
    }

    // ---- สรุปรายบุคคล ---------------------------------------------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function summary()
    {
        return $this->actingAs($this->admin())->get(route('technicians.rating.summary', $this->tech))->assertOk();
    }

    private function finishedJob(int $daysAgo, ?int $repairMinutes = null): MaintenanceRequest
    {
        $req = $this->closedDaysAgo($daysAgo, [
            'started_at' => $repairMinutes ? now()->subDays($daysAgo)->subMinutes($repairMinutes) : null,
            'resolved_at' => $repairMinutes ? now()->subDays($daysAgo) : null,
        ]);
        MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $this->tech->id, 'status' => 'done', 'is_lead' => true]);

        return $req;
    }

    public function test_the_summary_says_what_the_numbers_are_made_of(): void
    {
        $jobs = [$this->finishedJob(40, 90), $this->finishedJob(35, 30), $this->finishedJob(10, 150), $this->finishedJob(5), $this->finishedJob(3)];
        // five ratings: 5, 5, 4, 2, 1  → average 3.4, two of them 1–2 stars
        foreach ([5, 5, 4, 2, 1] as $i => $score) {
            $this->rate($jobs[$i], $score, $score <= 2 ? 'ควรปรับปรุง' : null);
        }

        $page = $this->summary();

        $this->assertSame(2, $page->viewData('lowCount'));
        $this->assertSame(['minutes' => 90, 'jobs' => 3], $page->viewData('repairTime'), 'the mean of 90, 30 and 150 minutes, over the jobs that have both times');
        $this->assertSame([5 => 2, 4 => 1, 3 => 0, 2 => 1, 1 => 1], collect($page->viewData('distribution'))->map(fn ($row) => (int) $row['count'])->all());
        $page->assertSee('3.40')
            ->assertSee('ปานกลาง')                                // 3.4 → fair
            ->assertSee('เวลาซ่อมเฉลี่ย 1 ชม. 30 นาที')
            ->assertSee('40% ของการประเมินทั้งหมด')                // 2 of 5
            ->assertSee('ผู้แจ้งประเมิน 100% ของงานที่เสร็จสิ้น')     // 5 ratings, 5 finished jobs
            ->assertDontSee('ยังไม่ควรใช้ตัดสินผลงาน');
    }

    public function test_the_trend_lists_six_months_oldest_first_and_a_month_without_ratings_is_shown_as_empty(): void
    {
        $this->rate($this->finishedJob(2), 5, null, now()->startOfMonth()->addHour());
        $this->rate($this->finishedJob(2), 3, null, now()->startOfMonth()->addHours(2));
        $this->rate($this->finishedJob(2), 4, null, now()->subMonths(2)->startOfMonth()->addDay());
        $this->rate($this->finishedJob(2), 1, null, now()->subMonths(7));   // older than the window: not in the trend

        $trend = $this->summary()->viewData('trend');

        $this->assertCount(6, $trend);
        $this->assertTrue($trend->first()['month']->lt($trend->last()['month']), 'oldest first');
        $this->assertSame([0, 0, 0, 1, 0, 2], $trend->pluck('count')->all());
        $this->assertSame([null, null, null, 4.0, null, 4.0], $trend->pluck('avg')->all());
    }

    public function test_the_summary_says_when_there_are_too_few_ratings_to_judge_by(): void
    {
        $this->rate($this->finishedJob(3), 5);

        $this->summary()
            ->assertSee('มีการประเมินเพียง 1 ครั้ง')
            ->assertSee('ยังไม่ควรใช้ตัดสินผลงาน');
    }

    public function test_a_person_nobody_rated_is_not_called_a_bad_one(): void
    {
        $page = $this->summary();

        $page->assertSee('ยังไม่มีการประเมินของเจ้าหน้าที่ท่านนี้')->assertDontSee('ควรปรับปรุง');
    }

    public function test_the_person_and_the_board_call_the_same_score_the_same_thing(): void
    {
        // 4.2 was "ดี" on the board and "ดีมาก" on the person's own page
        foreach ([5, 4, 4, 4, 4] as $score) {
            $this->rate($this->finishedJob(3), $score, null, null, User::factory()->create());
        }

        $board = $this->actingAs($this->admin())->get(route('maintenance.requests.rating.technicians'))->assertOk()->getContent();
        $person = $this->summary()->getContent();

        $this->assertMatchesRegularExpression('/>ดี<\/span>/u', $board);
        $this->assertMatchesRegularExpression('/>ดี<\/span>/u', $person);
        $this->assertStringNotContainsString('>ดีมาก<', $person);
        $this->assertStringNotContainsString('>ดีมาก<', $board);
    }

    public function test_recent_jobs_name_the_request_the_date_and_what_it_was_rated(): void
    {
        $rated = $this->finishedJob(6);
        $unrated = $this->finishedJob(2);
        $this->rate($rated, 4);

        $page = $this->summary();

        $page->assertSee('#' . $rated->request_no)->assertSee('#' . $unrated->request_no)
            ->assertSee(\App\Support\ThaiDate::short(now()->subDays(6)))
            ->assertSee('ยังไม่ได้ประเมิน');
        $this->assertSame([$rated->id => 4], $page->viewData('jobScores')->all());
    }

    public function test_a_comment_links_to_its_job_and_no_link_goes_nowhere(): void
    {
        $req = $this->finishedJob(3);
        $this->rate($req, 5, 'บริการดีเยี่ยม');

        $html = $this->summary()->assertSee('บริการดีเยี่ยม')->assertSee(route('maintenance.requests.show', $req), false)->getContent();

        $this->assertStringNotContainsString('href="#"', $html, 'the old "ดูประวัติทั้งหมด" link led nowhere');
        $this->assertStringNotContainsString('เร่งด่วน/วิกฤต', $html, 'a 1–2 star review is not an emergency');
        $this->assertDoesNotMatchRegularExpression('/\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\b/', strip_tags($html), 'English month names');
    }

    public function test_the_summary_uses_the_same_frame_as_the_other_pages(): void
    {
        $html = $this->summary()->getContent();

        $this->assertStringNotContainsString('max-w-screen-xl', $html, 'full width like the list pages');
        $this->assertStringContainsString('px-4 md:px-6 lg:px-8', $html);
        $this->assertMatchesRegularExpression('/<h1 class="text-\[17px\] font-semibold/', $html);
        $this->assertStringContainsString(route('maintenance.requests.rating.technicians'), $html, 'the back button');
    }

    public function test_the_summary_json_is_unchanged(): void
    {
        $this->rate($this->finishedJob(3), 5, 'ดี');

        $this->actingAs($this->admin())->getJson(route('technicians.rating.summary', $this->tech))
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'avatar_url', 'role_label', 'avg_score', 'total_count', 'reviews' => [['score', 'comment', 'created_at', 'rater']]]);
    }

    // ---- กระดานคะแนนเจ้าหน้าที่ --------------------------------------------------------------------------------------

    public function test_the_board_header_is_thai_with_a_material_symbol(): void
    {
        $html = $this->actingAs($this->admin())->get(route('maintenance.requests.rating.technicians'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-hidden="true">leaderboard</span>', $html);
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*สรุปผลการประเมินเจ้าหน้าที่/u', $html);
        $this->assertStringNotContainsString('popularity.webp', $html);
        $this->assertStringNotContainsString('Technician Evaluation Summary', $html);
    }

    public function test_a_technician_nobody_rated_is_listed_as_not_rated_yet(): void
    {
        $this->finishedJob(3);   // an assignment, no rating: still on the board

        $html = $this->actingAs($this->admin())->get(route('maintenance.requests.rating.technicians'))->assertOk()->getContent();

        $this->assertStringContainsString('ยังไม่มีการประเมิน', $html);
        $this->assertStringNotContainsString('>ควรปรับปรุง<', $html);
        $this->assertStringNotContainsString('0.00', strip_tags(explode('<tbody', $html)[1] ?? ''), 'no score of "0.00" for nobody');
    }

    public function test_the_board_rows_use_the_shared_button_and_stars(): void
    {
        $this->rate($this->finishedJob(3), 4);

        $html = $this->actingAs($this->admin())->get(route('maintenance.requests.rating.technicians'))->assertOk()->getContent();

        $this->assertStringContainsString(route('technicians.rating.summary', $this->tech), $html);
        $this->assertStringContainsString('>visibility</span>', $html);
        $this->assertStringContainsString('aria-label="4.0 จาก 5 ดาว"', $html);
        $this->assertStringNotContainsString('border-indigo-300', $html, 'the hand-made indigo link is gone');
    }
}
