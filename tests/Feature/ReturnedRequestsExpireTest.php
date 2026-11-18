<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest as MR;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A request sent back to its reporter ("ส่งกลับแก้ไข") waits for them for `repair.return_expires_after_days` (14) days; if they never fix and
 * send it again, the nightly `repair:expire-returned` cancels it, and until then the request, the list and the page count the days down.
 * Days are Thai calendar days: sent back on the 2nd it has 14 days left that day and is cancelled in the 03:30 sweep of the 16th.
 * The owner asked for exactly this on 2026-11-17: expire in 14 days, with a countdown the reporter can see and filter by.
 */
class ReturnedRequestsExpireTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Bangkok';

    private User $reporter;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporter = User::factory()->create(['role' => 'member']);
        $this->staff = User::factory()->create(['role' => 'it_support']);
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00', self::TZ));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A request sent back at this moment (through the real button, so status_updated_at and the history are the system's). */
    private function sentBack(string $at = '2026-11-02 10:00:00', array $overrides = []): MR
    {
        $job = MR::factory()->create(array_merge(['status' => 'pending', 'reporter_id' => $this->reporter->id, 'technician_id' => null, 'asset_id' => null], $overrides));
        Carbon::setTestNow(Carbon::parse($at, self::TZ));
        $this->actingAs($this->staff)->post(route('maintenance.requests.send-back', $job), ['return_reason' => 'ระบุห้องให้ชัดเจน']);
        $this->assertSame('returned', $job->fresh()->status);

        return $job->fresh();
    }

    private function at(string $moment): void
    {
        Carbon::setTestNow(Carbon::parse($moment, self::TZ));
    }

    private function sweep(array $options = []): int
    {
        return $this->artisan('repair:expire-returned', $options)->run();
    }

    // ---- the count ----------------------------------------------------------------------------------------------------

    public function test_the_days_left_are_thai_calendar_days_from_the_day_it_was_sent_back(): void
    {
        $job = $this->sentBack('2026-11-02 10:00:00');

        $this->at('2026-11-02 12:00:00');
        $this->assertSame(14, $job->returnDaysLeft(), 'the day it went back');
        $this->assertSame('2026-11-16', $job->returnExpiresOn()->toDateString());

        $this->at('2026-11-15 23:00:00');
        $this->assertSame(1, $job->returnDaysLeft());

        $this->at('2026-11-16 02:00:00');
        $this->assertSame(0, $job->returnDaysLeft(), 'the morning it is cancelled, before the sweep');
    }

    public function test_it_is_close_to_expiring_in_the_last_three_days_and_the_limit_is_a_setting(): void
    {
        $job = $this->sentBack('2026-11-02 10:00:00');

        $this->at('2026-11-12 10:00:00');
        $this->assertFalse($job->returnIsExpiring(), '4 days left');
        $this->at('2026-11-13 10:00:00');
        $this->assertTrue($job->returnIsExpiring(), '3 days left');

        config(['repair.return_expires_after_days' => 7]);
        $this->at('2026-11-02 12:00:00');
        $this->assertSame(7, $job->returnDaysLeft(), 'the number of days is a setting');

        config(['repair.return_expires_after_days' => 0]);
        $this->assertNull($job->returnDaysLeft(), 'off at 0');
        $this->assertNull($job->returnExpiresOn());
    }

    public function test_only_a_returned_request_has_a_countdown(): void
    {
        $job = MR::factory()->create(['status' => 'pending', 'reporter_id' => $this->reporter->id]);

        $this->assertNull($job->returnDaysLeft());
        $this->assertNull($job->returnExpiresOn());
        $this->assertFalse($job->returnIsExpiring());
    }

    // ---- the sweep ----------------------------------------------------------------------------------------------------

    public function test_the_sweep_cancels_a_request_whose_fourteen_days_are_up_and_says_why_in_its_history(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'EX-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null]);
        $job = $this->sentBack('2026-11-02 10:00:00', ['asset_id' => $asset->id]);
        $this->assertSame('in_repair', $asset->fresh()->status);

        $this->at('2026-11-15 03:30:00');
        $this->sweep();
        $this->assertSame('returned', $job->fresh()->status, 'the 15th: one day left');

        $this->at('2026-11-16 03:30:00');
        $this->sweep();

        $this->assertSame('cancelled', $job->fresh()->status);
        $this->assertSame('active', $asset->fresh()->status, 'a cancelled request frees its asset');
        $log = DB::table('maintenance_logs')->where('request_id', $job->id)->where('to_status', 'cancelled')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->user_id, 'done by the system, nobody pressed anything');
        $this->assertStringContainsString('ยกเลิกอัตโนมัติ', $log->note);
        $this->assertStringContainsString('14 วัน', $log->note);
    }

    public function test_the_sweep_leaves_alone_everything_that_has_time_or_is_not_returned(): void
    {
        $fresh = $this->sentBack('2026-11-10 10:00:00');
        $old = ['status_updated_at' => Carbon::parse('2026-09-01', self::TZ)];
        $others = [];
        foreach (['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold', 'resolved', 'closed'] as $status) {
            $others[$status] = MR::factory()->create(['status' => $status, 'reporter_id' => $this->reporter->id, 'asset_id' => null] + $old);
        }

        $this->at('2026-11-16 03:30:00');
        $this->sweep();

        $this->assertSame('returned', $fresh->fresh()->status);
        foreach ($others as $status => $job) {
            $this->assertSame($status, $job->fresh()->status, "$status is not the sweep's business, however old");
        }
    }

    public function test_a_dry_run_changes_nothing_and_zero_turns_the_rule_off(): void
    {
        $job = $this->sentBack('2026-10-01 10:00:00');
        $this->at('2026-11-16 03:30:00');

        $this->sweep(['--dry-run' => true]);
        $this->assertSame('returned', $job->fresh()->status);

        config(['repair.return_expires_after_days' => 0]);
        $this->sweep();
        $this->assertSame('returned', $job->fresh()->status, 'off');

        config(['repair.return_expires_after_days' => 14]);
        $this->sweep();
        $this->assertSame('cancelled', $job->fresh()->status);
        $this->sweep();   // and a second night finds nothing more to do
        $this->assertSame(1, DB::table('maintenance_logs')->where('request_id', $job->id)->where('to_status', 'cancelled')->count());
    }

    public function test_sending_it_again_and_back_again_starts_a_new_fourteen_days(): void
    {
        $job = $this->sentBack('2026-11-02 10:00:00');

        $this->at('2026-11-10 10:00:00');
        $this->actingAs($this->reporter)->post(route('maintenance.requests.resubmit', $job));
        $this->actingAs($this->staff)->post(route('maintenance.requests.send-back', $job), ['return_reason' => 'ยังไม่ชัด']);
        $this->assertSame('returned', $job->fresh()->status);

        $this->at('2026-11-16 03:30:00');   // the first deadline has passed; the new one is the 24th
        $this->sweep();

        $this->assertSame('returned', $job->fresh()->status);
        $this->assertSame(8, $job->fresh()->returnDaysLeft(), 'sent back again on the 10th: it runs to the 24th, 8 days from the 16th');
    }

    public function test_it_runs_every_night_at_03_30_thai_time_after_the_chat_sweeps(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'repair:expire-returned'));

        $this->assertNotNull($event, 'scheduled');
        $this->assertSame('30 3 * * *', $event->expression);
        $this->assertSame(self::TZ, (string) $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    // ---- what the reporter sees -----------------------------------------------------------------------------------------

    public function test_the_job_page_says_when_it_will_be_cancelled_and_how_many_days_are_left(): void
    {
        $job = $this->sentBack('2026-11-02 10:00:00');
        $this->at('2026-11-04 09:00:00');

        foreach ([$this->reporter, $this->staff] as $viewer) {
            $this->actingAs($viewer)->get(route('maintenance.requests.show', $job))->assertOk()
                ->assertSee('id="returned-notice"', false)
                ->assertSee('เหลือ 12 วัน')
                ->assertSee('ยกเลิกอัตโนมัติ');
        }
    }

    public function test_the_list_shows_the_countdown_on_a_returned_request_only_and_filters_by_it(): void
    {
        $soon = $this->sentBack('2026-11-02 10:00:00');          // 14 days from the 2nd
        $later = $this->sentBack('2026-11-12 10:00:00');
        $pending = MR::factory()->create(['status' => 'pending', 'reporter_id' => $this->reporter->id, 'asset_id' => null]);
        $this->at('2026-11-14 12:00:00');                          // $soon has 2 days left, $later 12

        $ids = fn (array $query) => collect($this->actingAs($this->reporter)->get(route('maintenance.requests.index', $query))->assertOk()->viewData('list')->items())->pluck('id')->sort()->values()->all();

        $all = $this->actingAs($this->reporter)->get(route('maintenance.requests.index'))->assertOk();
        $all->assertSee('เหลือ 2 วัน')->assertSee('เหลือ 12 วัน');
        $this->assertSame(2, substr_count($all->getContent(), 'data-returned-countdown'), 'one badge per returned request, none on the pending one');

        $this->assertSame([$soon->id, $later->id], $ids(['status' => 'returned']), 'sent back, all of them');
        $this->assertSame([$soon->id], $ids(['status' => 'returned_expiring']), 'sent back and close to expiring (3 days or fewer)');
        $this->assertContains($pending->id, $ids([]));
        $this->assertNotContains($pending->id, $ids(['status' => 'returned_expiring']));
    }

    public function test_the_status_filter_offers_the_close_to_expiring_choice(): void
    {
        $page = $this->actingAs($this->reporter)->get(route('maintenance.requests.index'))->assertOk();

        $page->assertSee('value="returned_expiring"', false)->assertSee('ส่งกลับแก้ไข — ใกล้หมดอายุ');
    }

    public function test_the_close_to_expiring_choice_is_not_offered_when_nothing_expires(): void
    {
        config(['repair.return_expires_after_days' => 0]);

        $page = $this->actingAs($this->reporter)->get(route('maintenance.requests.index'))->assertOk();

        $page->assertDontSee('value="returned_expiring"', false)->assertDontSee('ใกล้หมดอายุ');
        $page->assertSee('value="returned"', false);   // sent back is still a status to filter by
    }
}
