<?php

namespace Tests\Feature;

use App\Events\ChatMessageDeleted;
use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * One message can be deleted - by whoever wrote it while the thread is open, or by a moderator (admins and the IT / repair team) at any time.
 * It stays in the thread as "ข้อความนี้ถูกลบ" for everybody, its text is never sent again, everyone with the thread open hears it, the thread
 * does not jump to the top of the list, and the moderation record says who did it. Locking, unlocking and deleting a thread are recorded too.
 */
class ChatDeleteMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatMessageSent::class, ChatMessageDeleted::class]);
    }

    private function talk(bool $locked = false): array
    {
        $owner = User::factory()->create(['role' => 'member']);
        $writer = User::factory()->create(['role' => 'member']);
        $thread = ChatThread::create(['title' => 'เครื่องพิมพ์ชั้น 2', 'author_id' => $owner->id, 'is_locked' => $locked]);
        $mine = ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $writer->id, 'body' => 'ข้อความลับของผม']);
        $theirs = ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $owner->id, 'body' => 'ข้อความของเจ้าของ']);

        return [$thread, $writer, $mine, $owner, $theirs];
    }

    private function remove(User $who, ChatThread $thread, ChatMessage $message)
    {
        return $this->actingAs($who)->deleteJson(route('chat.messages.destroy', [$thread, $message]));
    }

    // ---- who may -----------------------------------------------------------------------------------------------

    public function test_the_author_deletes_their_own_message_while_the_thread_is_open(): void
    {
        [$thread, $writer, $mine] = $this->talk();

        $this->remove($writer, $thread, $mine)->assertOk()->assertJsonPath('deleted', true);

        $this->assertSoftDeleted('chat_messages', ['id' => $mine->id]);
        Event::assertDispatched(ChatMessageDeleted::class, fn ($e) => $e->threadId === $thread->id && $e->messageId === $mine->id);
    }

    public function test_a_person_cannot_delete_somebody_elses_message(): void
    {
        [$thread, $writer, , , $theirs] = $this->talk();

        $this->remove($writer, $thread, $theirs)->assertForbidden();

        $this->assertNotSoftDeleted('chat_messages', ['id' => $theirs->id]);
        Event::assertNotDispatched(ChatMessageDeleted::class);
    }

    public function test_not_even_the_threads_owner_deletes_a_message_that_is_not_theirs(): void
    {
        [$thread, , $mine, $owner] = $this->talk();

        $this->remove($owner, $thread, $mine)->assertForbidden();
    }

    /** @return array<string,array{0:string}> */
    public static function moderators(): array
    {
        return ['admin' => ['admin'], 'it_support' => ['it_support'], 'network' => ['network'], 'programmer' => ['programmer'], 'technician' => ['technician']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('moderators')]
    public function test_a_moderator_deletes_any_message_even_in_a_locked_thread(string $role): void
    {
        [$thread, , $mine] = $this->talk(true);

        $this->remove(User::factory()->create(['role' => $role]), $thread, $mine)->assertOk();

        $this->assertSoftDeleted('chat_messages', ['id' => $mine->id]);
    }

    public function test_a_supervisor_is_not_a_moderator(): void
    {
        [$thread, , $mine] = $this->talk();

        $this->remove(User::factory()->create(['role' => 'supervisor']), $thread, $mine)->assertForbidden();
    }

    public function test_the_author_cannot_change_a_locked_threads_history(): void
    {
        [$thread, $writer, $mine] = $this->talk(true);

        $this->remove($writer, $thread, $mine)->assertForbidden();

        $this->assertNotSoftDeleted('chat_messages', ['id' => $mine->id]);
    }

    public function test_a_message_of_another_thread_is_a_404_and_a_guest_is_turned_away(): void
    {
        [$thread, $writer] = $this->talk();
        [, , $foreign] = $this->talk();

        $this->remove($writer, $thread, $foreign)->assertNotFound();
    }

    public function test_a_guest_is_turned_away(): void
    {
        [$thread, , $mine] = $this->talk();

        $this->deleteJson(route('chat.messages.destroy', [$thread, $mine]))->assertUnauthorized();
        $this->assertNotSoftDeleted('chat_messages', ['id' => $mine->id]);
    }

    public function test_deleting_twice_is_a_404_the_second_time(): void
    {
        [$thread, $writer, $mine] = $this->talk();

        $this->remove($writer, $thread, $mine)->assertOk();
        $this->remove($writer, $thread, $mine)->assertNotFound();
    }

    public function test_a_plain_form_post_goes_back_with_a_thai_toast_when_refused(): void
    {
        [$thread, $writer, , , $theirs] = $this->talk();

        $this->actingAs($writer)->from('/chat')->delete(route('chat.messages.destroy', [$thread, $theirs]))
            ->assertRedirect('/chat')
            ->assertSessionHas('toast.message', 'เฉพาะเจ้าของข้อความและผู้ดูแลเท่านั้นที่ลบข้อความได้');
    }

    // ---- what is left ------------------------------------------------------------------------------------------

    public function test_the_page_draws_a_deleted_message_as_a_placeholder_and_never_sends_its_words(): void
    {
        [$thread, $writer, $mine, $owner] = $this->talk();
        $this->remove($writer, $thread, $mine)->assertOk();

        $html = $this->actingAs($owner)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();

        $this->assertStringNotContainsString('ข้อความลับของผม', $html);
        $this->assertStringContainsString('ข้อความนี้ถูกลบ', $html);
        $this->assertStringContainsString('data-message-id="' . $mine->id . '"', $html);
        $this->assertStringContainsString('ข้อความของเจ้าของ', $html, 'the others stay');
    }

    public function test_the_bin_is_offered_only_where_the_person_may_use_it(): void
    {
        [$thread, $writer, $mine, $owner, $theirs] = $this->talk();
        $bins = fn (User $who) => substr_count($this->actingAs($who)->get(route('chat.index', ['thread_id' => $thread->id]))->getContent(), 'aria-label="ลบข้อความนี้"');

        $this->assertSame(1, $bins($writer), 'only their own');
        $this->assertSame(1, $bins($owner), 'the owner has one message of their own, not the writer\'s');
        $this->assertSame(2, $bins(User::factory()->create(['role' => 'it_support'])), 'a moderator: both');
        $this->assertSame(0, $bins(User::factory()->create(['role' => 'supervisor'])));

        $thread->update(['is_locked' => true]);
        $this->assertSame(0, $bins($writer), 'a locked thread: not for its writers');
        $this->assertSame(2, $bins(User::factory()->create(['role' => 'admin'])));
    }

    public function test_the_box_tells_the_page_whether_the_person_moderates(): void
    {
        [$thread, $writer] = $this->talk();

        $this->assertStringContainsString('data-can-moderate="0"', $this->actingAs($writer)->get(route('chat.index', ['thread_id' => $thread->id]))->getContent());
        $this->assertStringContainsString('data-can-moderate="1"', $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('chat.index', ['thread_id' => $thread->id]))->getContent());
    }

    public function test_the_unread_count_and_the_message_count_do_not_include_it(): void
    {
        [$thread, $writer, $mine, $owner] = $this->talk();
        $this->remove($writer, $thread, $mine)->assertOk();

        $this->assertSame(1, $thread->messages()->count());
        $page = $this->actingAs($owner)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk();
        $this->assertSame(1, $page->viewData('totalMessages'));
    }

    public function test_deleting_a_message_does_not_bring_the_thread_to_the_top_of_the_list(): void
    {
        [$thread, $writer, $mine] = $this->talk();
        $before = Carbon::create(2026, 1, 1, 8, 0, 0);
        ChatThread::whereKey($thread->id)->update(['updated_at' => $before]);

        $this->remove($writer, $thread, $mine)->assertOk();

        $this->assertTrue($thread->fresh()->updated_at->equalTo($before), 'still as it was');
    }

    // ---- the record --------------------------------------------------------------------------------------------

    public function test_a_deletion_is_recorded_with_who_which_and_from_where_but_not_the_words(): void
    {
        [$thread, $writer, $mine] = $this->talk();
        $mod = User::factory()->create(['role' => 'it_support']);

        $this->actingAs($mod)->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->deleteJson(route('chat.messages.destroy', [$thread, $mine]))->assertOk();

        $log = ChatModerationLog::firstOrFail();
        $this->assertSame(ChatModerationLog::DELETE_MESSAGE, $log->action);
        $this->assertSame($mod->id, $log->actor_id);
        $this->assertSame($thread->id, $log->chat_thread_id);
        $this->assertSame($mine->id, $log->chat_message_id);
        $this->assertSame('10.1.2.3', $log->ip);
        $this->assertSame($writer->id, $log->meta['message_author_id']);
        $this->assertFalse($log->meta['own']);
        $this->assertSame('เครื่องพิมพ์ชั้น 2', $log->meta['thread_title']);
        $this->assertStringNotContainsString('ข้อความลับของผม', json_encode($log->meta, JSON_UNESCAPED_UNICODE));
    }

    public function test_locking_unlocking_and_deleting_a_thread_are_recorded_too(): void
    {
        [$thread, , , $owner] = $this->talk();
        $staff = User::factory()->create(['role' => 'it_support']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($staff)->postJson(route('chat.lock', $thread))->assertOk();
        $this->actingAs($staff)->postJson(route('chat.unlock', $thread))->assertOk();
        $this->actingAs($owner)->delete(route('chat.destroy', $thread));

        $this->assertSame(
            [ChatModerationLog::LOCK, ChatModerationLog::UNLOCK, ChatModerationLog::DELETE_THREAD],
            ChatModerationLog::orderBy('id')->pluck('action')->all(),
        );
        $this->assertSame([$staff->id, $staff->id, $owner->id], ChatModerationLog::orderBy('id')->pluck('actor_id')->all());
        $this->assertTrue(ChatModerationLog::where('action', ChatModerationLog::DELETE_THREAD)->first()->meta['own'], 'the owner deleting their own thread');

        $other = ChatThread::create(['title' => 'อื่น', 'author_id' => $owner->id, 'is_locked' => false]);
        $this->actingAs($admin)->delete(route('chat.destroy', $other));
        $this->assertFalse(ChatModerationLog::where('chat_thread_id', $other->id)->first()->meta['own']);
    }

    public function test_a_refused_action_leaves_no_record_and_the_record_outlives_a_purged_thread(): void
    {
        [$thread, $writer, , , $theirs] = $this->talk();
        $this->remove($writer, $thread, $theirs)->assertForbidden();
        $this->assertSame(0, ChatModerationLog::count());

        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson(route('chat.lock', $thread))->assertOk();
        $thread->forceDelete();

        $this->assertSame(1, ChatModerationLog::count(), 'the thread id is a plain number, not a foreign key');
    }

    public function test_the_api_deletes_and_records_the_same_way(): void
    {
        [$thread, $writer, $mine] = $this->talk();
        Sanctum::actingAs($writer);

        $this->deleteJson("/api/threads/{$thread->id}/messages/{$mine->id}")->assertOk()->assertJsonPath('deleted', true);

        $this->assertSoftDeleted('chat_messages', ['id' => $mine->id]);
        $this->assertSame(1, ChatModerationLog::where('action', ChatModerationLog::DELETE_MESSAGE)->count());

        [$thread2, $writer2, , , $theirs2] = $this->talk();
        Sanctum::actingAs($writer2);
        $this->deleteJson("/api/threads/{$thread2->id}/messages/{$theirs2->id}")->assertForbidden();
    }

    public function test_the_api_lock_is_recorded(): void
    {
        [$thread] = $this->talk();
        Sanctum::actingAs(User::factory()->create(['role' => 'technician']));

        $this->postJson("/api/threads/{$thread->id}/lock")->assertOk();

        $this->assertSame(ChatModerationLog::LOCK, ChatModerationLog::firstOrFail()->action);
    }
}
