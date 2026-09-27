<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\ChatThreadDeleted;
use App\Events\Chat\ChatThreadLockChanged;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A conversation is not kept for ever (PDPA: personal data with no reason to sit in the database). A thread nobody has written in, or
 * unlocked, for 90 days is LOCKED by the nightly `chat:expire-idle`; one that stays locked 90 days with nobody unlocking it is DELETED (hidden -
 * chat:purge-deleted erases it 30 days later, and until then chat:restore brings it back). The thread says so, ahead of time.
 */
class ChatIdleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function thread(string $title = 'ห้อง'): ChatThread
    {
        return ChatThread::create(['title' => $title, 'author_id' => User::factory()->create(['role' => 'member'])->id, 'is_locked' => false]);
    }

    /** An open thread whose last word was $days ago (updated_at is the last word; a raw update keeps it from being touched). */
    private function idle(int $days, string $title = 'เงียบ'): ChatThread
    {
        $thread = $this->thread($title);
        DB::table('chat_threads')->where('id', $thread->id)->update(['updated_at' => now()->subDays($days)]);

        return $thread->refresh();
    }

    /** A locked thread that was locked $days ago. */
    private function lockedFor(int $days, string $title = 'ล็อก'): ChatThread
    {
        $thread = $this->thread($title);
        $thread->forceFill(['is_locked' => true, 'locked_at' => now()->subDays($days)])->save();

        return $thread->refresh();
    }

    // ---- when it was locked ------------------------------------------------------------------------------------------

    public function test_locking_records_when_and_unlocking_clears_it_whoever_does_it(): void
    {
        $thread = $this->thread();
        $this->assertNull($thread->locked_at);

        $staff = User::factory()->create(['role' => 'it_support']);
        $this->actingAs($staff)->post(route('chat.lock', $thread))->assertRedirect();
        $this->assertNotNull($thread->fresh()->locked_at, 'by hand, on the page');

        $this->actingAs($staff)->post(route('chat.unlock', $thread))->assertRedirect();
        $this->assertNull($thread->fresh()->locked_at);

        Sanctum::actingAs($staff);
        $this->postJson("/api/threads/{$thread->id}/lock")->assertOk();
        $this->assertNotNull($thread->fresh()->locked_at, 'through the API');
        $this->postJson("/api/threads/{$thread->id}/unlock")->assertOk();
        $this->assertNull($thread->fresh()->locked_at);

        $seeded = ChatThread::create(['title' => 'x', 'author_id' => $thread->author_id, 'is_locked' => true]);
        $this->assertNotNull($seeded->fresh()->locked_at, 'a thread created locked (a seeder, a factory) has it too');
    }

    // ---- 1. idle threads are locked ---------------------------------------------------------------------------------

    public function test_a_thread_nobody_wrote_in_for_90_days_is_locked_and_a_newer_one_is_left(): void
    {
        Event::fake([ChatThreadLockChanged::class]);
        $old = $this->idle(91, 'เก่า');
        $edge = $this->idle(89, 'เกือบ');

        $this->artisan('chat:expire-idle')->expectsOutputToContain('locked 1 idle thread(s)')->assertSuccessful();

        $this->assertTrue((bool) $old->fresh()->is_locked);
        $this->assertNotNull($old->fresh()->locked_at);
        $this->assertFalse((bool) $edge->fresh()->is_locked);
        Event::assertDispatched(ChatThreadLockChanged::class, fn ($e) => $e->threadId === $old->id && $e->isLocked === true);
        Event::assertDispatchedTimes(ChatThreadLockChanged::class, 1);
    }

    public function test_the_sweep_is_not_a_new_word_the_thread_keeps_its_place_and_the_moderation_record_names_no_person(): void
    {
        $old = $this->idle(120);
        $before = $old->updated_at->toDateTimeString();

        $this->artisan('chat:expire-idle')->assertSuccessful();

        $this->assertSame($before, $old->fresh()->updated_at->toDateTimeString(), 'not moved to the top of every list');
        $log = ChatModerationLog::where('chat_thread_id', $old->id)->where('action', ChatModerationLog::AUTO_LOCK)->first();
        $this->assertNotNull($log);
        $this->assertNull($log->actor_id, 'the system, not a person');
        $this->assertSame(90, $log->meta['idle_days']);
    }

    public function test_a_new_message_restarts_the_count(): void
    {
        $thread = $this->idle(100);
        ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $thread->author_id, 'body' => 'ยังอยู่']);

        $this->artisan('chat:expire-idle')->assertSuccessful();

        $this->assertFalse((bool) $thread->fresh()->is_locked);
    }

    public function test_unlocking_a_thread_the_sweep_locked_does_not_get_it_locked_again_that_night(): void
    {
        $thread = $this->idle(100);
        $this->artisan('chat:expire-idle')->assertSuccessful();
        $this->assertTrue((bool) $thread->fresh()->is_locked);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('chat.unlock', $thread))->assertRedirect();
        $this->artisan('chat:expire-idle')->assertSuccessful();

        $this->assertFalse((bool) $thread->fresh()->is_locked, 'the unlock is a new start');
    }

    // ---- 2. long-locked threads are deleted ------------------------------------------------------------------------

    public function test_a_thread_locked_for_more_than_90_days_is_deleted_and_hidden_not_erased(): void
    {
        Event::fake([ChatThreadDeleted::class]);
        $gone = $this->lockedFor(91, 'ต้องลบ');
        $kept = $this->lockedFor(89, 'ยังไม่ครบ');

        $this->artisan('chat:expire-idle')->expectsOutputToContain('deleted 1 thread(s)')->assertSuccessful();

        $this->assertNull(ChatThread::find($gone->id));
        $this->assertNotNull(ChatThread::onlyTrashed()->find($gone->id), 'still there for chat:restore, until the purge');
        $this->assertNotNull(ChatThread::find($kept->id));
        $log = ChatModerationLog::where('chat_thread_id', $gone->id)->where('action', ChatModerationLog::AUTO_DELETE)->first();
        $this->assertNotNull($log);
        $this->assertNull($log->actor_id);
        Event::assertDispatched(ChatThreadDeleted::class, fn ($e) => $e->threadId === $gone->id);
    }

    public function test_an_open_thread_is_never_deleted_however_old_it_is_it_is_locked_first(): void
    {
        $old = $this->idle(400);

        $this->artisan('chat:expire-idle')->assertSuccessful();

        $this->assertNotNull(ChatThread::find($old->id));
        $this->assertTrue((bool) $old->fresh()->is_locked);
    }

    public function test_the_whole_road_idle_then_locked_then_deleted_then_erased(): void
    {
        $start = Carbon::parse('2027-01-10 12:00:00');
        Carbon::setTestNow($start);
        $thread = $this->thread('ทั้งเส้นทาง');
        ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $thread->author_id, 'body' => 'ข้อความสุดท้าย']);

        Carbon::setTestNow($start->copy()->addDays(91));
        $this->artisan('chat:expire-idle')->assertSuccessful();
        $this->assertTrue((bool) $thread->fresh()->is_locked, 'locked after 90 days of silence');

        Carbon::setTestNow($start->copy()->addDays(91 + 91));
        $this->artisan('chat:expire-idle')->assertSuccessful();
        $this->assertNull(ChatThread::find($thread->id), 'deleted after 90 more days locked');

        Carbon::setTestNow($start->copy()->addDays(91 + 91 + 31));
        $this->artisan('chat:purge-deleted')->assertSuccessful();
        $this->assertNull(ChatThread::withTrashed()->find($thread->id), 'erased for good 30 days later');
        $this->assertSame(0, ChatMessage::withTrashed()->where('chat_thread_id', $thread->id)->count());
    }

    public function test_a_thread_brought_back_by_restore_gets_the_whole_period_again(): void
    {
        $thread = $this->lockedFor(100);
        $this->artisan('chat:expire-idle')->assertSuccessful();
        $this->assertNull(ChatThread::find($thread->id));

        $this->artisan('chat:restore', ['thread' => $thread->id])->assertSuccessful();
        $this->artisan('chat:expire-idle')->assertSuccessful();

        $this->assertNotNull(ChatThread::find($thread->id), 'not deleted again the next night');
        $this->assertTrue($thread->fresh()->locked_at->gt(now()->subMinute()));
    }

    // ---- settings ----------------------------------------------------------------------------------------------------

    public function test_each_rule_is_off_at_zero_and_dry_run_changes_nothing(): void
    {
        $idle = $this->idle(500, 'a');
        $locked = $this->lockedFor(500, 'b');

        config(['chat.lock_idle_after_days' => 0, 'chat.delete_locked_after_days' => 0]);
        $this->artisan('chat:expire-idle')->expectsOutputToContain('locked 0 idle thread(s)')->assertSuccessful();
        $this->assertFalse((bool) $idle->fresh()->is_locked);
        $this->assertNotNull(ChatThread::find($locked->id));

        config(['chat.lock_idle_after_days' => 90, 'chat.delete_locked_after_days' => 90]);
        $this->artisan('chat:expire-idle', ['--dry-run' => true])->expectsOutputToContain('would lock 1 idle thread(s) and would delete 1 thread(s)')->assertSuccessful();
        $this->assertFalse((bool) $idle->fresh()->is_locked);
        $this->assertNotNull(ChatThread::find($locked->id));
        $this->assertSame(0, ChatModerationLog::whereIn('action', [ChatModerationLog::AUTO_LOCK, ChatModerationLog::AUTO_DELETE])->count());
    }

    public function test_it_runs_every_night_at_03_00_thai_time_before_the_purge(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'chat:expire-idle'));

        $this->assertNotNull($event, 'scheduled');
        $this->assertSame('0 3 * * *', $event->expression);
        $this->assertSame('Asia/Bangkok', (string) $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    // ---- the thread says so ------------------------------------------------------------------------------------------

    public function test_the_dates_a_thread_announces_are_the_morning_the_sweep_will_really_act(): void
    {
        // last word at 14:00 Thai time on 5 Jan: 90 days on is 5 Apr 14:00, and the first 03:00 sweep after that is on the 6th
        Carbon::setTestNow(Carbon::parse('2027-01-05 14:00:00', 'Asia/Bangkok'));
        $this->assertSame('2027-04-06', $this->thread()->autoLocksOn()->toDateString());

        // ... a last word at 01:00 on the 5th reaches its 90 days at 01:00 on 5 Apr: the sweep at 03:00 that same morning
        Carbon::setTestNow(Carbon::parse('2027-01-05 01:00:00', 'Asia/Bangkok'));
        $this->assertSame('2027-04-05', $this->thread()->autoLocksOn()->toDateString());

        // the same reckoning for a lock: locked at 01:00 -> deleted at 03:00 the morning it turns 90 days; locked at 14:00 -> the morning after
        $this->assertSame('2027-04-05', $this->lockedFor(0)->autoDeletesOn()->toDateString());
        Carbon::setTestNow(Carbon::parse('2027-01-05 14:00:00', 'Asia/Bangkok'));
        $this->assertSame('2027-04-06', $this->lockedFor(0)->autoDeletesOn()->toDateString());
    }

    public function test_a_locked_thread_page_says_when_it_will_be_deleted(): void
    {
        $thread = $this->lockedFor(10);
        $user = User::factory()->create(['role' => 'member']);

        $html = $this->actingAs($user)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();

        $this->assertStringContainsString('หากไม่มีการปลดล็อก กระทู้นี้จะถูกลบอัตโนมัติในวันที่ ' . \App\Support\ThaiDate::long($thread->autoDeletesOn()), $html);
        $this->assertStringContainsString('หากไม่มีการปลดล็อกภายใน 90 วัน กระทู้นี้จะถูกลบอัตโนมัติ', $html, 'and the rule, for a lock made while the page is open');
    }

    public function test_an_open_thread_warns_only_in_the_last_14_days_before_its_lock(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $near = $this->idle(80, 'ใกล้ล็อก');
        $far = $this->idle(30, 'ยังห่าง');

        $nearHtml = $this->actingAs($user)->get(route('chat.index', ['thread_id' => $near->id]))->getContent();
        $farHtml = $this->actingAs($user)->get(route('chat.index', ['thread_id' => $far->id]))->getContent();

        $this->assertStringContainsString('id="idleLockWarning"', $nearHtml);
        $this->assertStringContainsString('จะถูกล็อกอัตโนมัติในวันที่ ' . \App\Support\ThaiDate::long($near->autoLocksOn()), $nearHtml);
        $this->assertStringNotContainsString('id="idleLockWarning"', $farHtml);
        $this->assertStringNotContainsString('หากไม่มีการปลดล็อก กระทู้นี้จะถูกลบอัตโนมัติในวันที่', $farHtml, 'an open thread has no deletion date');
    }

    public function test_the_create_dialog_states_the_rule_and_it_follows_the_settings(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $html = $this->actingAs($user)->get(route('chat.index'))->getContent();
        $this->assertStringContainsString('กระทู้ที่ไม่มีการตอบครบ 90 วันจะถูกล็อกอัตโนมัติ และกระทู้ที่ถูกล็อกครบ 90 วันโดยไม่มีการปลดล็อกจะถูกลบอัตโนมัติ', $html);

        config(['chat.lock_idle_after_days' => 30, 'chat.delete_locked_after_days' => 0]);
        $html = $this->actingAs($user)->get(route('chat.index'))->getContent();
        $this->assertStringContainsString('กระทู้ที่ไม่มีการตอบครบ 30 วันจะถูกล็อกอัตโนมัติ', $html);
        $this->assertStringNotContainsString('จะถูกลบอัตโนมัติ', $html);

        config(['chat.lock_idle_after_days' => 0]);
        $this->assertNull(ChatThread::lifecycleNotice());
    }

    public function test_the_api_thread_carries_the_dates_for_a_client_to_show(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'member']));
        $near = $this->idle(80);
        $far = $this->idle(10);
        $locked = $this->lockedFor(5);

        $this->getJson("/api/threads/{$near->id}")->assertOk()
            ->assertJsonPath('auto_lock_on', $near->autoLocksOn()->toDateString())->assertJsonPath('auto_delete_on', null)->assertJsonPath('locked_at', null);
        $this->getJson("/api/threads/{$far->id}")->assertOk()->assertJsonPath('auto_lock_on', null);
        $this->getJson("/api/threads/{$locked->id}")->assertOk()
            ->assertJsonPath('auto_delete_on', $locked->autoDeletesOn()->toDateString())->assertJsonPath('auto_lock_on', null);
    }
}
