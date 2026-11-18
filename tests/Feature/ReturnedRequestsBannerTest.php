<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest as MR;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A reporter whose request was sent back ("ส่งกลับแก้ไข") is told so where they land: a banner on the dashboard and above the list, with the
 * number of their requests waiting for them, how many are close to being cancelled automatically, and a link to the list already filtered.
 * The owner asked for it on 2026-11-18 as the small feature of the day, on top of the countdown (PR #41).
 */
class ReturnedRequestsBannerTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Bangkok';

    private User $reporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporter = User::factory()->create(['role' => 'member']);
        Carbon::setTestNow(Carbon::parse('2026-11-14 12:00:00', self::TZ));   // a request sent back on the 2nd has 2 days left
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A request sent back with this many days left on the countdown (14 days, counted in Thai calendar days from the day it went back). */
    private function sentBack(int $daysLeft, ?User $reporter = null, array $overrides = []): MR
    {
        $wentBack = Carbon::parse('2026-11-14 09:00:00', self::TZ)->startOfDay()->addDays($daysLeft - 14)->setTime(9, 0);

        return MR::factory()->create(array_merge([
            'status' => 'returned', 'reporter_id' => ($reporter ?? $this->reporter)->id,
            'technician_id' => null, 'asset_id' => null, 'status_updated_at' => $wentBack,
        ], $overrides));
    }

    private function dashboard(?User $as = null)
    {
        return $this->actingAs($as ?? $this->reporter)->get(route('repair.dashboard'))->assertOk();
    }

    private function list(array $query = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->reporter)->get(route('maintenance.requests.index', $query))->assertOk();
    }

    public function test_a_reporter_with_requests_sent_back_is_told_how_many_on_the_dashboard_and_above_the_list(): void
    {
        $this->sentBack(12);
        $this->sentBack(9);

        foreach ([$this->dashboard(), $this->list()] as $page) {
            $page->assertSee('data-returned-banner', false)
                ->assertSee('ถูกส่งกลับให้แก้ไข 2 รายการ')
                ->assertSee(route('maintenance.requests.index', ['status' => 'returned']), false);
        }
    }

    public function test_there_is_no_banner_when_nothing_of_theirs_is_waiting_for_them(): void
    {
        foreach (['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold', 'resolved', 'closed', 'cancelled', 'rejected'] as $status) {
            MR::factory()->create(['status' => $status, 'reporter_id' => $this->reporter->id, 'asset_id' => null]);
        }
        $deleted = $this->sentBack(10);
        $deleted->delete();

        $this->dashboard()->assertDontSee('data-returned-banner', false);
        $this->list()->assertDontSee('data-returned-banner', false);
    }

    public function test_it_counts_only_what_the_viewer_reported_even_for_an_admin_who_sees_every_request(): void
    {
        $other = User::factory()->create(['role' => 'member']);
        $this->sentBack(10, $other);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->dashboard($admin)->assertDontSee('data-returned-banner', false);
        $this->list([], $admin)->assertDontSee('data-returned-banner', false);
        $this->dashboard($this->reporter)->assertDontSee('data-returned-banner', false);

        $this->sentBack(10, $admin);
        $this->list([], $admin)->assertSee('ถูกส่งกลับให้แก้ไข 1 รายการ');
    }

    public function test_it_says_how_many_are_close_to_being_cancelled_and_links_to_exactly_those(): void
    {
        $this->sentBack(2);
        $this->sentBack(3);
        $this->sentBack(12);

        $page = $this->list();

        $page->assertSee('ถูกส่งกลับให้แก้ไข 3 รายการ')
            ->assertSee('2 รายการใกล้หมดอายุ')
            ->assertSee('เหลือ 2 วัน')   // the soonest one
            ->assertSee(route('maintenance.requests.index', ['status' => 'returned_expiring']), false);
    }

    public function test_without_any_close_to_expiring_it_does_not_cry_wolf(): void
    {
        $this->sentBack(4);   // one day outside the warning
        $this->sentBack(14);

        $page = $this->dashboard();

        $page->assertSee('ถูกส่งกลับให้แก้ไข 2 รายการ')
            ->assertDontSee('ใกล้หมดอายุ')
            ->assertDontSee('status=returned_expiring', false);
    }

    public function test_the_banner_and_the_list_filter_agree_on_what_close_to_expiring_means(): void
    {
        $three = $this->sentBack(3);
        $four = $this->sentBack(4);

        $this->list()->assertSee('1 รายการใกล้หมดอายุ');
        $filtered = $this->list(['status' => 'returned_expiring'])->viewData('list')->items();

        $this->assertSame([$three->id], collect($filtered)->pluck('id')->all(), '3 days left is close, 4 is not, for the banner and the filter alike');
        $this->assertNotContains($four->id, collect($filtered)->pluck('id')->all());
    }

    public function test_the_list_does_not_repeat_the_banner_when_they_are_already_looking_at_those_requests(): void
    {
        $this->sentBack(2);

        $this->list(['status' => 'returned'])->assertDontSee('data-returned-banner', false);
        $this->list(['status' => 'returned_expiring'])->assertDontSee('data-returned-banner', false);
        $this->list(['status' => 'pending'])->assertSee('data-returned-banner', false);
        $this->dashboard()->assertSee('data-returned-banner', false);
    }

    public function test_with_the_expiry_rule_off_it_still_tells_them_but_promises_no_cancellation(): void
    {
        config(['repair.return_expires_after_days' => 0]);
        $this->sentBack(1);

        $this->list()->assertSee('ถูกส่งกลับให้แก้ไข 1 รายการ')
            ->assertDontSee('ใกล้หมดอายุ')
            ->assertDontSee('ยกเลิกอัตโนมัติ');
    }
}
