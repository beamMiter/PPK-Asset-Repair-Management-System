<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Deleting in the chat only hides (so it can be brought back). What has been deleted for 30 days is erased for good by the nightly
 * `chat:purge-deleted` - the thread with its messages and read marks, and single messages deleted from live threads - because a deleted
 * conversation kept in the database forever is personal data kept for no reason. The moderation record says what was erased, never the words.
 */
class ChatPurgeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function thread(string $title = 'ห้อง', ?User $author = null): ChatThread
    {
        $author ??= User::factory()->create(['role' => 'member']);

        return ChatThread::create(['title' => $title, 'author_id' => $author->id, 'is_locked' => false]);
    }

    private function say(ChatThread $thread, string $body, ?User $who = null): ChatMessage
    {
        return ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => ($who ?? $thread->author)->id, 'body' => $body]);
    }

    private function deletedDaysAgo($model, int $days): void
    {
        $model::withTrashed()->whereKey($model->id)->update(['deleted_at' => now()->subDays($days)]);
    }

    public function test_a_thread_deleted_more_than_30_days_ago_is_erased_with_its_messages_and_read_marks(): void
    {
        $thread = $this->thread('ต้องลบ');
        $this->say($thread, 'ข้อความลับ 1');
        $this->say($thread, 'ข้อความลับ 2');
        DB::table('chat_thread_reads')->insert(['user_id' => $thread->author_id, 'chat_thread_id' => $thread->id, 'created_at' => now(), 'updated_at' => now()]);
        $thread->delete();
        $this->deletedDaysAgo($thread, 31);

        $this->artisan('chat:purge-deleted')->expectsOutputToContain('erased 1 thread(s) and 2 message(s)')->assertSuccessful();

        $this->assertNull(ChatThread::withTrashed()->find($thread->id));
        $this->assertSame(0, ChatMessage::withTrashed()->where('chat_thread_id', $thread->id)->count());
        $this->assertSame(0, DB::table('chat_thread_reads')->where('chat_thread_id', $thread->id)->count());
    }

    public function test_a_thread_deleted_29_days_ago_is_still_there_to_be_brought_back(): void
    {
        $thread = $this->thread();
        $this->say($thread, 'x');
        $thread->delete();
        $this->deletedDaysAgo($thread, 29);

        $this->artisan('chat:purge-deleted')->assertSuccessful();

        $this->assertNotNull(ChatThread::onlyTrashed()->find($thread->id));
        $this->assertSame(1, $thread->messages()->withTrashed()->count());
    }

    public function test_a_live_thread_is_never_touched_however_old_it_is(): void
    {
        $old = $this->thread('เก่ามาก');
        $this->say($old, 'ยังอยู่');
        ChatThread::whereKey($old->id)->update(['created_at' => now()->subYears(2), 'updated_at' => now()->subYears(2)]);

        $this->artisan('chat:purge-deleted')->assertSuccessful();

        $this->assertNotNull(ChatThread::find($old->id));
        $this->assertSame(1, $old->messages()->count());
    }

    public function test_a_single_message_deleted_long_ago_is_erased_from_a_live_thread_and_the_others_stay(): void
    {
        $thread = $this->thread();
        $keep = $this->say($thread, 'อยู่ต่อ');
        $recent = $this->say($thread, 'เพิ่งลบ');
        $old = $this->say($thread, 'ลบนานแล้ว');
        $recent->delete();
        $old->delete();
        $this->deletedDaysAgo($old, 45);
        $this->deletedDaysAgo($recent, 5);

        $this->artisan('chat:purge-deleted')->expectsOutputToContain('erased 0 thread(s) and 1 message(s)')->assertSuccessful();

        $this->assertNull(ChatMessage::withTrashed()->find($old->id));
        $this->assertNotNull(ChatMessage::onlyTrashed()->find($recent->id), 'still within the 30 days');
        $this->assertNotNull(ChatMessage::find($keep->id));
    }

    public function test_the_moderation_record_says_what_was_erased_and_never_the_words_and_outlives_it(): void
    {
        $thread = $this->thread('หัวข้อที่ถูกล้าง');
        $this->say($thread, 'ข้อความที่ห้ามเหลือ');
        $thread->delete();
        $this->deletedDaysAgo($thread, 40);

        $this->artisan('chat:purge-deleted')->assertSuccessful();

        $log = ChatModerationLog::where('action', ChatModerationLog::PURGE_THREAD)->firstOrFail();
        $this->assertNull($log->actor_id, 'the system');
        $this->assertSame($thread->id, $log->chat_thread_id);
        $this->assertSame('หัวข้อที่ถูกล้าง', $log->meta['thread_title']);
        $this->assertSame(1, $log->meta['messages_erased']);
        $this->assertStringNotContainsString('ข้อความที่ห้ามเหลือ', json_encode($log->meta, JSON_UNESCAPED_UNICODE));
    }

    public function test_dry_run_says_what_it_would_erase_and_erases_nothing(): void
    {
        $thread = $this->thread();
        $this->say($thread, 'x');
        $thread->delete();
        $this->deletedDaysAgo($thread, 60);

        $this->artisan('chat:purge-deleted --dry-run')->expectsOutputToContain('would erase 1 thread(s) and 1 message(s)')->assertSuccessful();

        $this->assertNotNull(ChatThread::onlyTrashed()->find($thread->id));
        $this->assertSame(0, ChatModerationLog::count());
    }

    public function test_the_number_of_days_is_a_setting_and_an_option_and_zero_turns_it_off(): void
    {
        $thread = $this->thread();
        $thread->delete();
        $this->deletedDaysAgo($thread, 10);

        $this->artisan('chat:purge-deleted')->assertSuccessful();
        $this->assertNotNull(ChatThread::onlyTrashed()->find($thread->id), '10 days is inside the default 30');

        config(['chat.purge_deleted_after_days' => 7]);
        $this->artisan('chat:purge-deleted --days=60')->assertSuccessful();
        $this->assertNotNull(ChatThread::onlyTrashed()->find($thread->id), 'the option wins: 60');

        $this->artisan('chat:purge-deleted')->assertSuccessful();
        $this->assertNull(ChatThread::withTrashed()->find($thread->id), 'the setting: 7');
    }

    public function test_zero_days_is_off(): void
    {
        $thread = $this->thread();
        $thread->delete();
        $this->deletedDaysAgo($thread, 400);
        config(['chat.purge_deleted_after_days' => 0]);

        $this->artisan('chat:purge-deleted')->expectsOutputToContain('Purging is off')->assertSuccessful();

        $this->assertNotNull(ChatThread::onlyTrashed()->find($thread->id));
    }

    public function test_it_runs_every_night_at_03_10_thai_time(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'chat:purge-deleted'));

        $this->assertNotNull($event, 'scheduled');
        $this->assertSame('10 3 * * *', $event->expression);
        $this->assertSame('Asia/Bangkok', (string) $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    // ---- bringing one back ------------------------------------------------------------------------------------------

    public function test_a_deleted_thread_can_be_brought_back_until_it_is_purged(): void
    {
        $thread = $this->thread('กู้คืน');
        $this->say($thread, 'ยังอยู่ครบ');
        $thread->delete();

        $this->artisan('chat:restore ' . $thread->id)->expectsOutputToContain('is back')->assertSuccessful();

        $this->assertNotNull(ChatThread::find($thread->id));
        $this->assertSame(1, $thread->messages()->count());
        $this->assertSame(ChatModerationLog::RESTORE_THREAD, ChatModerationLog::firstOrFail()->action);
    }

    public function test_a_purged_or_a_live_thread_cannot_be_restored(): void
    {
        $gone = $this->thread();
        $gone->delete();
        $this->deletedDaysAgo($gone, 50);
        $this->artisan('chat:purge-deleted')->assertSuccessful();
        $live = $this->thread();

        $this->artisan('chat:restore ' . $gone->id)->assertFailed();
        $this->artisan('chat:restore ' . $live->id)->assertFailed();
        $this->artisan('chat:restore 999999')->assertFailed();
    }
}
