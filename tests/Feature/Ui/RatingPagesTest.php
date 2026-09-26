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

    private function evaluate(array $query = [])
    {
        return $this->actingAs($this->member)->get(route('maintenance.requests.rating.evaluate', $query))->assertOk();
    }

    // ---- ประเมินความพึงพอใจ ----------------------------------------------------------------------------------------

    public function test_the_job_that_runs_out_of_time_first_comes_first_and_says_how_long_is_left(): void
    {
        $plenty = $this->closedDaysAgo(5);     // 25 days left
        $tight = $this->closedDaysAgo(28);     // 2 days left
        $middle = $this->closedDaysAgo(15);    // 15 days left

        $page = $this->evaluate();

        $this->assertSame([$tight->id, $middle->id, $plenty->id], $page->viewData('requests')->pluck('id')->all());
        $this->assertSame([2, 15, 25], $page->viewData('requests')->pluck('rating_days_left')->all());
        $page->assertSee('เหลือ 2 วัน')->assertSee('เหลือ 15 วัน')->assertSee('เหลือ 25 วัน');
    }

    /** The last day is a whole day: 30 days and 23 hours after closing it can still be rated and is listed; 31 days and a minute cannot. */
    public function test_the_last_day_runs_until_the_job_is_31_days_old(): void
    {
        $lateInLastDay = $this->closedDaysAgo(0, ['closed_at' => now()->subDays(30)->subHours(23)]);
        $justOver = $this->closedDaysAgo(0, ['closed_at' => now()->subDays(31)->subMinute()]);
        foreach ([$lateInLastDay, $justOver] as $req) {
            MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $this->tech->id, 'status' => 'done', 'is_lead' => true]);
        }

        $ids = $this->evaluate()->viewData('requests')->pluck('id')->all();

        $this->assertContains($lateInLastDay->id, $ids);
        $this->assertNotContains($justOver->id, $ids);
        $this->actingAs($this->member)->get(route('maintenance.requests.rating.create', $lateInLastDay))->assertRedirect(route('maintenance.requests.show', $lateInLastDay));
        $this->assertNull(session('toast.message'), 'the guard agrees with the list: still allowed');
        $this->flushSession();
        $this->actingAs($this->member)->get(route('maintenance.requests.rating.create', $justOver));
        $this->assertSame('เลยระยะเวลาที่สามารถให้คะแนนงานนี้ได้แล้ว', session('toast.message'));
    }

    public function test_the_last_day_and_the_day_after_it(): void
    {
        $last = $this->closedDaysAgo(30);
        $gone = $this->closedDaysAgo(31);
        foreach ([$last, $gone] as $req) {   // the guard attributes a rating to a team member of the job
            MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $this->tech->id, 'status' => 'done', 'is_lead' => true]);
        }

        $page = $this->evaluate();

        $ids = $page->viewData('requests')->pluck('id')->all();
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
        foreach (range(1, 25) as $i) {
            $this->closedDaysAgo(26);
        }

        $page = $this->evaluate();

        $this->assertSame(25, $page->viewData('expiringCount'));
        $this->assertCount(20, $page->viewData('requests')->items());
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

        $this->assertSame([$closedLongAgo->id, $closedRecently->id], $this->evaluate(['tab' => 'rated'])->viewData('requests')->pluck('id')->all());
    }

    public function test_the_history_shows_the_stars_the_words_and_the_date_of_the_rating(): void
    {
        $req = $this->closedDaysAgo(10);
        $this->rate($req, 4, 'ช่างมาเร็ว', now()->subDays(3));

        $this->evaluate(['tab' => 'rated'])
            ->assertSee('ช่างมาเร็ว')
            ->assertSee('4.0 - พอใจ')
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

        foreach (['pending', 'rated'] as $tab) {
            $html = $this->evaluate(['tab' => $tab])->getContent();

            $this->assertSame(1, substr_count($html, 'hidden md:block overflow-x-auto'), "$tab: a desktop table");
            $this->assertSame(1, substr_count($html, 'md:hidden grid gap-3'), "$tab: a card list on a phone");
            $this->assertStringNotContainsString('rounded-sm', $html, 'the old square-cornered cards are gone');
        }
    }

    public function test_a_state_is_coloured_text_not_a_boxed_label(): void
    {
        $this->closedDaysAgo(3);
        $this->closedDaysAgo(28);
        $this->rate($this->closedDaysAgo(9), 5);

        foreach (['pending', 'rated'] as $tab) {
            $html = $this->evaluate(['tab' => $tab])->getContent();

            // the request list and the asset list write a status as plain coloured text; a ring / tinted chip here was the odd one out
            $this->assertDoesNotMatchRegularExpression('/\bring-1\b/', $html, "$tab: a ringed chip");
        }
    }

    // ---- one list at a time, made for a long list --------------------------------------------------------------------

    public function test_the_two_lists_are_tabs_and_each_counts_everything_whatever_the_other_shows(): void
    {
        foreach (range(1, 3) as $i) {
            $this->closedDaysAgo($i);
        }
        $this->rate($this->closedDaysAgo(9), 4);
        $this->rate($this->closedDaysAgo(10), 5);

        foreach ([[], ['tab' => 'rated'], ['tab' => 'pending', 'q' => 'nothing matches this']] as $query) {
            $page = $this->evaluate($query);

            $this->assertSame(3, $page->viewData('pendingCount'), json_encode($query));
            $this->assertSame(2, $page->viewData('totalRatedCount'), json_encode($query));
        }

        $html = $this->evaluate()->getContent();
        $this->assertStringContainsString(route('maintenance.requests.rating.evaluate', ['tab' => 'rated']), str_replace('&amp;', '&', $html));
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*รอประเมิน\s*<span[^>]*>3</u', $html);
    }

    public function test_only_the_list_on_show_is_on_the_page(): void
    {
        $pending = $this->closedDaysAgo(3, ['title' => 'งานที่ยังไม่ประเมิน']);
        $rated = $this->closedDaysAgo(9, ['title' => 'งานที่ประเมินแล้ว']);
        $this->rate($rated, 5);

        $this->evaluate()->assertSee('งานที่ยังไม่ประเมิน')->assertDontSee('งานที่ประเมินแล้ว');
        $this->evaluate(['tab' => 'rated'])->assertSee('งานที่ประเมินแล้ว')->assertDontSee('งานที่ยังไม่ประเมิน');
        $this->evaluate(['tab' => 'nonsense'])->assertSee('งานที่ยังไม่ประเมิน');   // an unknown tab is the waiting list
    }

    public function test_twenty_to_a_page_and_the_page_says_where_you_are(): void
    {
        foreach (range(1, 45) as $i) {
            $this->closedDaysAgo(3);
        }

        $first = $this->evaluate();
        $this->assertCount(20, $first->viewData('requests')->items());
        $first->assertSee('หน้า 1 จาก 3');

        $this->assertCount(5, $this->evaluate(['page' => 3])->viewData('requests')->items());
    }

    public function test_the_search_finds_a_job_by_number_title_place_or_technician(): void
    {
        $byTitle = $this->closedDaysAgo(3, ['title' => 'เครื่องพิมพ์ชั้นสาม']);
        $byPlace = $this->closedDaysAgo(4, ['title' => 'อย่างอื่น', 'location_text' => 'ห้องผ่าตัด 2']);
        $other = User::factory()->create(['role' => 'it_support', 'name' => 'ช่างวิเชียร']);
        $byTech = $this->closedDaysAgo(5, ['title' => 'งานสาม', 'technician_id' => $other->id]);
        $unrelated = $this->closedDaysAgo(6, ['title' => 'ไม่เกี่ยวเลย', 'location_text' => 'ที่อื่น']);

        $ids = fn (string $q) => $this->evaluate(['q' => $q])->viewData('requests')->pluck('id')->all();

        $this->assertSame([$byTitle->id], $ids('เครื่องพิมพ์'));
        $this->assertSame([$byPlace->id], $ids('ห้องผ่าตัด'));
        $this->assertSame([$byTech->id], $ids('วิเชียร'));
        $this->assertSame([$unrelated->id], $ids($unrelated->request_no));
    }

    public function test_the_search_never_shows_somebody_elses_job(): void
    {
        $stranger = User::factory()->create(['role' => 'member']);
        $theirs = MaintenanceRequest::factory()->create([
            'reporter_id' => $stranger->id, 'status' => MaintenanceRequest::STATUS_CLOSED, 'closed_at' => now()->subDay(), 'title' => 'ชื่อเดียวกัน',
        ]);
        $mine = $this->closedDaysAgo(2, ['title' => 'ชื่อเดียวกัน']);

        $this->assertSame([$mine->id], $this->evaluate(['q' => 'ชื่อเดียวกัน'])->viewData('requests')->pluck('id')->all());
    }

    public function test_a_percent_or_underscore_in_the_search_is_text_not_a_wildcard(): void
    {
        $exact = $this->closedDaysAgo(3, ['title' => 'ลด 50% ค่าซ่อม']);
        $this->closedDaysAgo(4, ['title' => 'ลด 500 บาท']);
        $under = $this->closedDaysAgo(5, ['title' => 'PC_OPD_01']);
        $this->closedDaysAgo(6, ['title' => 'PCxOPDx01']);

        $ids = fn (string $q) => $this->evaluate(['q' => $q])->viewData('requests')->pluck('id')->all();

        $this->assertSame([$exact->id], $ids('50%'));
        $this->assertSame([$under->id], $ids('PC_OPD'));
        $this->assertSame([], $ids('%%%'), 'three percent signs are not "everything"');
    }

    public function test_the_waiting_list_can_be_narrowed_to_the_jobs_about_to_run_out(): void
    {
        $this->closedDaysAgo(3);
        $soon = $this->closedDaysAgo(25);
        $soonest = $this->closedDaysAgo(28);

        $page = $this->evaluate(['urgency' => 'soon']);

        $this->assertSame([$soonest->id, $soon->id], $page->viewData('requests')->pluck('id')->all());
        $page->assertSee('พบ');
    }

    public function test_the_history_can_be_narrowed_to_one_score(): void
    {
        $five = $this->closedDaysAgo(9);
        $two = $this->closedDaysAgo(10);
        $this->rate($five, 5, 'ดีเยี่ยม');
        $this->rate($two, 2, 'ช้ามาก');

        $ids = fn (array $q) => $this->evaluate(['tab' => 'rated'] + $q)->viewData('requests')->pluck('id')->all();

        $this->assertSame([$two->id], $ids(['score' => 2]));
        $this->assertSame([$five->id], $ids(['score' => 5]));
        $this->assertEqualsCanonicalizing([$five->id, $two->id], $ids([]));
        $this->assertEqualsCanonicalizing([$five->id, $two->id], $ids(['score' => 9]), 'a score that is not 1–5 is no filter');
    }

    public function test_an_empty_result_says_nothing_matched_and_how_to_clear_it(): void
    {
        $this->closedDaysAgo(3);

        $this->evaluate(['q' => 'ไม่มีชื่อแบบนี้'])
            ->assertSee('ไม่พบรายการที่ตรงกับคำค้นหาหรือตัวกรอง')
            ->assertSee('ล้างค่าทั้งหมด')
            ->assertDontSee('ไม่มีงานค้างประเมิน');   // that one is for a list that is empty, not one that was narrowed to nothing
    }

    public function test_the_search_and_the_filter_are_kept_when_paging(): void
    {
        foreach (range(1, 25) as $i) {
            $this->closedDaysAgo(3, ['title' => "เครื่องพิมพ์ {$i}"]);
        }

        $html = str_replace('&amp;', '&', $this->evaluate(['q' => 'เครื่องพิมพ์', 'urgency' => ''])->getContent());

        $this->assertMatchesRegularExpression('/href="[^"]*(?=[^"]*page=2)(?=[^"]*q=)[^"]*"/u', urldecode($html));
    }

    public function test_the_magnifier_does_not_sit_on_the_text_of_the_search_box(): void
    {
        $html = $this->evaluate()->getContent();

        preg_match('/<input id="q"[^>]*>/', $html, $tag);
        $this->assertNotEmpty($tag, 'the search box');
        preg_match('/class="([^"]*)"/', $tag[0], $class);
        $classes = preg_split('/\s+/', $class[1]);

        // the icon is 36px wide at the left edge: the text starts after it (pl-10) …
        $this->assertContains('pl-10', $classes);
        // … and no `px-N` in the same list: Bootstrap makes px-3 / px-4 / px-5 !important, so it would win over pl-10 and the
        // text would start under the icon (the users page writes `pl-10 pr-3` for the same reason)
        $this->assertSame([], array_values(array_filter($classes, fn ($c) => preg_match('/^px-[0-5]$/', $c))));
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_jobs(): void
    {
        $queries = function (string $tab): int {
            \Illuminate\Support\Facades\DB::flushQueryLog();
            \Illuminate\Support\Facades\DB::enableQueryLog();
            $this->evaluate(['tab' => $tab]);
            $n = count(\Illuminate\Support\Facades\DB::getQueryLog());
            \Illuminate\Support\Facades\DB::disableQueryLog();

            return $n;
        };

        foreach (range(1, 3) as $i) {
            $this->closedDaysAgo($i);
            $this->rate($this->closedDaysAgo(10 + $i), 4, 'ดี');
        }
        [$fewPending, $fewRated] = [$queries('pending'), $queries('rated')];

        foreach (range(1, 17) as $i) {
            $this->closedDaysAgo(2 + $i % 20, ['technician_id' => User::factory()->create(['role' => 'it_support'])->id]);
            $this->rate($this->closedDaysAgo(8, ['technician_id' => User::factory()->create(['role' => 'it_support'])->id]), 5, 'ดี');
        }

        $this->assertSame($fewPending, $queries('pending'), 'waiting list: an extra query per row');
        $this->assertSame($fewRated, $queries('rated'), 'history: an extra query per row');
    }

    public function test_nonsense_in_the_query_string_is_nothing_not_an_error(): void
    {
        $this->closedDaysAgo(3);

        foreach ([
            ['tab' => ['x'], 'q' => ['y'], 'score' => 'abc', 'urgency' => ['z'], 'page' => 'abc'],
            ['tab' => 'rated', 'score' => '-1', 'q' => str_repeat('ก', 5000), 'page' => '999999'],
            ['q' => "x'\"%_\\", 'urgency' => 'soon; drop table'],
        ] as $query) {
            $this->actingAs($this->member)->get(route('maintenance.requests.rating.evaluate') . '?' . http_build_query($query))->assertOk();
        }
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
        // No English date for the dates on the page. Looked for as the very strings PHP / Carbon would print for THESE two dates: a
        // pattern over the whole page ("a month next to a number") also matched the factory's random names, streets and phone numbers,
        // and this test failed on those about one run in eight.
        foreach ([$req->closed_at, \App\Models\MaintenanceRating::firstOrFail()->created_at] as $date) {
            foreach (['j M Y', 'd M Y', 'M j, Y', 'j F Y', 'F j, Y', 'M Y', 'F Y'] as $format) {
                $this->assertStringNotContainsString($date->format($format), strip_tags($html), "English date ($format)");
            }
        }
    }

    public function test_the_comments_are_the_ratings_that_have_one_newest_first_six_at_most(): void
    {
        $withComment = [];
        foreach (range(1, 8) as $i) {   // 8 commented ratings, one a day apart
            $withComment[$i] = $this->rate($this->finishedJob(20 + $i), 4, "ความเห็นที่ {$i}", now()->subDays(20 - $i), User::factory()->create());
        }
        $this->rate($this->finishedJob(3), 5, null, now()->subHour(), User::factory()->create());   // the newest rating, but no words
        $this->rate($this->finishedJob(3), 5, '', now()->subMinutes(30), User::factory()->create());

        $page = $this->summary();
        $comments = $page->viewData('comments');

        $this->assertCount(6, $comments);
        $this->assertSame(collect(range(8, 3))->map(fn ($i) => $withComment[$i]->id)->all(), $comments->pluck('id')->all(), 'newest comment first');
        $page->assertSee('ความเห็นที่ 8')->assertDontSee('ความเห็นที่ 2')
            ->assertDontSee('ไม่มีข้อความความคิดเห็น');   // a card with no words told the reader nothing: it is not listed at all
    }

    public function test_a_person_with_ratings_but_no_comments_says_so(): void
    {
        $this->rate($this->finishedJob(3), 5);

        $this->summary()->assertSee('ยังไม่มีความคิดเห็น');
    }

    public function test_each_comment_wears_the_avatar_the_system_uses_for_that_person(): void
    {
        $plain = User::factory()->create(['name' => 'สมหญิง ใจดี']);   // no photo: the initials avatar
        $this->rate($this->finishedJob(3), 5, 'ดีมาก', null, $plain);

        $page = $this->summary()->assertSee('สมหญิง ใจดี');
        $html = $page->getContent();

        $this->assertStringStartsWith('data:image/svg+xml', $plain->avatar_thumb_url);
        $this->assertStringContainsString('src="' . e($plain->avatar_thumb_url) . '"', $html, 'the same default avatar as the user list and the chat');
        $this->assertStringNotContainsString('bg-emerald-600 text-[13px] font-bold text-white', $html, 'the hand-made initials circle is gone');
    }

    public function test_a_rater_with_a_photo_shows_the_photo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('avatars/9-128.webp', 'x');
        $withPhoto = User::factory()->create(['profile_photo_thumb' => 'avatars/9-128.webp']);
        $this->rate($this->finishedJob(3), 4, 'บริการดี', null, $withPhoto);

        $html = $this->summary()->getContent();

        $this->assertStringContainsString('src="' . e(\Illuminate\Support\Facades\Storage::url('avatars/9-128.webp')) . '"', $html);
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
