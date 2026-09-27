<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\ChatMessageSent;
use App\Events\Chat\ChatMessageUpdated;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A message can be edited by whoever wrote it, while the thread is open - nobody else, a moderator included (a moderator may delete somebody's
 * words, not put other words under their name), and nobody once the thread is locked. The row says "แก้ไขแล้ว", everyone who has the thread open
 * hears it, the thread does not jump to the top or start its idle count again, and the moderation record says that it happened, never the words.
 */
class ChatEditMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatMessageSent::class, ChatMessageUpdated::class]);
    }

    /** @return array{0: ChatThread, 1: User, 2: ChatMessage, 3: User, 4: ChatMessage} thread, writer, writer's message, owner, owner's message */
    private function talk(bool $locked = false): array
    {
        $owner = User::factory()->create(['role' => 'member']);
        $writer = User::factory()->create(['role' => 'member']);
        $thread = ChatThread::create(['title' => 'เครื่องพิมพ์ชั้น 2', 'author_id' => $owner->id, 'is_locked' => $locked]);
        $mine = ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $writer->id, 'body' => 'ข้อความเดิมของผม']);
        $theirs = ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $owner->id, 'body' => 'ข้อความของเจ้าของ']);

        return [$thread, $writer, $mine, $owner, $theirs];
    }

    private function change(User $who, ChatThread $thread, ChatMessage $message, ?string $body)
    {
        return $this->actingAs($who)->patchJson(route('chat.messages.update', [$thread, $message]), ['body' => $body]);
    }

    // ---- who may -----------------------------------------------------------------------------------------------

    public function test_the_author_edits_their_own_message_while_the_thread_is_open(): void
    {
        [$thread, $writer, $mine] = $this->talk();

        $this->change($writer, $thread, $mine, 'ข้อความใหม่')
            ->assertOk()
            ->assertJsonPath('body', 'ข้อความใหม่')
            ->assertJsonPath('edited', true);

        $fresh = $mine->fresh();
        $this->assertSame('ข้อความใหม่', $fresh->body);
        $this->assertNotNull($fresh->edited_at);
        Event::assertDispatched(ChatMessageUpdated::class, fn ($e) => $e->threadId === $thread->id && $e->messageId === $mine->id && $e->body === 'ข้อความใหม่' && $e->editedAt !== null);
    }

    public function test_nobody_else_edits_it_a_moderator_and_an_admin_included(): void
    {
        [$thread, , $mine, $owner] = $this->talk();

        foreach ([$owner, User::factory()->create(['role' => 'it_support']), User::factory()->create(['role' => 'admin']), User::factory()->create(['role' => 'supervisor'])] as $who) {
            $this->change($who, $thread, $mine, 'ใส่คำอื่นใต้ชื่อเขา')->assertForbidden();
        }

        $this->assertSame('ข้อความเดิมของผม', $mine->fresh()->body);
        $this->assertNull($mine->fresh()->edited_at);
        Event::assertNotDispatched(ChatMessageUpdated::class);
    }

    public function test_a_guest_cannot(): void
    {
        [$thread, , $mine] = $this->talk();

        $this->patchJson(route('chat.messages.update', [$thread, $mine]), ['body' => 'x'])->assertUnauthorized();
    }

    public function test_nobody_edits_in_a_locked_thread(): void
    {
        [$thread, $writer, $mine] = $this->talk(locked: true);

        $this->change($writer, $thread, $mine, 'ใหม่')->assertForbidden();
        $this->change(User::factory()->create(['role' => 'admin']), $thread, $mine, 'ใหม่')->assertForbidden();

        $this->assertSame('ข้อความเดิมของผม', $mine->fresh()->body);
    }

    public function test_a_deleted_message_and_a_message_of_another_thread_are_not_found(): void
    {
        [$thread, $writer, $mine] = $this->talk();
        [$other, , $elsewhere] = $this->talk();

        $this->change($writer, $thread, $elsewhere, 'ใหม่')->assertNotFound();   // not this thread's message

        $mine->delete();
        $this->change($writer, $thread, $mine, 'ใหม่')->assertNotFound();
    }

    // ---- what it does ----------------------------------------------------------------------------------------

    public function test_saving_the_same_words_is_not_an_edit(): void
    {
        [$thread, $writer, $mine] = $this->talk();

        $this->change($writer, $thread, $mine, 'ข้อความเดิมของผม')->assertOk()->assertJsonPath('edited', false);

        $this->assertNull($mine->fresh()->edited_at);
        Event::assertNotDispatched(ChatMessageUpdated::class);
        $this->assertSame(0, ChatModerationLog::where('action', ChatModerationLog::EDIT_MESSAGE)->count());
    }

    public function test_an_edit_does_not_move_the_thread_or_restart_its_idle_count(): void
    {
        [$thread, $writer, $mine] = $this->talk();
        DB::table('chat_threads')->where('id', $thread->id)->update(['updated_at' => now()->subDays(80)]);
        $before = $thread->fresh()->updated_at->toDateTimeString();

        $this->change($writer, $thread, $mine, 'แก้ข้อความเก่า')->assertOk();

        $this->assertSame($before, $thread->fresh()->updated_at->toDateTimeString(), 'the thread keeps its place in every list and the day the silence began');
    }

    public function test_the_edit_is_recorded_without_the_words(): void
    {
        [$thread, $writer, $mine] = $this->talk();

        $this->change($writer, $thread, $mine, 'คำลับใหม่')->assertOk();

        $log = ChatModerationLog::where('action', ChatModerationLog::EDIT_MESSAGE)->firstOrFail();
        $this->assertSame($writer->id, $log->actor_id);
        $this->assertSame($mine->id, $log->chat_message_id);
        $this->assertSame($thread->id, $log->chat_thread_id);
        $encoded = json_encode($log->meta, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('คำลับใหม่', $encoded);
        $this->assertStringNotContainsString('ข้อความเดิมของผม', $encoded);
    }

    public function test_the_words_may_be_edited_again_and_again(): void
    {
        [$thread, $writer, $mine] = $this->talk();

        $this->change($writer, $thread, $mine, 'หนึ่ง')->assertOk();
        $this->change($writer, $thread, $mine, 'สอง')->assertOk()->assertJsonPath('body', 'สอง');

        $this->assertSame('สอง', $mine->fresh()->body);
    }

    // ---- what it accepts -------------------------------------------------------------------------------------

    public function test_the_words_follow_the_rules_of_a_new_message(): void
    {
        [$thread, $writer, $mine] = $this->talk();

        $this->change($writer, $thread, $mine, '')->assertUnprocessable();
        $this->change($writer, $thread, $mine, '   ')->assertUnprocessable();
        $this->change($writer, $thread, $mine, str_repeat('ก', 3001))->assertUnprocessable();
        $this->change($writer, $thread, $mine, str_repeat('ก', 3000))->assertOk();
        $this->assertSame(3000, mb_strlen($mine->fresh()->body));
    }

    public function test_the_route_is_throttled_like_sending(): void
    {
        $route = app('router')->getRoutes()->getByName('chat.messages.update');

        $this->assertContains('throttle:chat-message', $route->gatherMiddleware());
        $this->assertContains('throttle:chat-message', app('router')->getRoutes()->getByName('messages.update')->gatherMiddleware());
    }

    // ---- what a page reads afterwards -------------------------------------------------------------------------

    public function test_a_message_loaded_afterwards_says_it_was_edited_and_a_deleted_one_never_does(): void
    {
        [$thread, $writer, $mine, $owner, $theirs] = $this->talk();
        $this->change($writer, $thread, $mine, 'แก้แล้ว')->assertOk();
        $this->change($owner, $thread, $theirs, 'แก้แล้วเหมือนกัน')->assertOk();
        $theirs->delete();

        $json = $this->actingAs($writer)->getJson(route('chat.messages', $thread) . '?before_id=' . ($theirs->id + 1))->assertOk()->json();
        $byId = collect($json)->keyBy('id');

        $this->assertTrue($byId[$mine->id]['edited']);
        $this->assertNotNull($byId[$mine->id]['edited_at']);
        $this->assertFalse($byId[$theirs->id]['edited'], 'a deleted message carries no words and no mark');
        $this->assertNull($byId[$theirs->id]['edited_at']);
    }

    // ---- the API ---------------------------------------------------------------------------------------------

    public function test_the_api_edits_with_the_same_rules_and_record(): void
    {
        [$thread, $writer, $mine, $owner] = $this->talk();

        Sanctum::actingAs($owner, ['*']);
        $this->patchJson("/api/threads/{$thread->id}/messages/{$mine->id}", ['body' => 'ไม่ใช่ของฉัน'])->assertForbidden();

        Sanctum::actingAs($writer, ['*']);
        $this->patchJson("/api/threads/{$thread->id}/messages/{$mine->id}", ['body' => ''])->assertUnprocessable();
        $this->patchJson("/api/threads/{$thread->id}/messages/{$mine->id}", ['body' => 'จาก API'])
            ->assertOk()->assertJsonPath('body', 'จาก API')->assertJsonPath('edited', true);

        $this->assertSame('จาก API', $mine->fresh()->body);
        Event::assertDispatched(ChatMessageUpdated::class);
        $this->assertSame(1, ChatModerationLog::where('action', ChatModerationLog::EDIT_MESSAGE)->count());
        $this->getJson("/api/threads/{$thread->id}/messages")->assertOk()->assertJsonPath('data.0.edited', true);
    }

    public function test_the_api_refuses_a_locked_thread_and_a_deleted_message(): void
    {
        [$thread, $writer, $mine] = $this->talk();
        Sanctum::actingAs($writer, ['*']);

        $thread->forceFill(['is_locked' => true])->save();
        $this->patchJson("/api/threads/{$thread->id}/messages/{$mine->id}", ['body' => 'x'])->assertForbidden();

        $thread->forceFill(['is_locked' => false])->save();
        $mine->delete();
        $this->patchJson("/api/threads/{$thread->id}/messages/{$mine->id}", ['body' => 'x'])->assertNotFound();
    }

    // ---- the page --------------------------------------------------------------------------------------------

    private function page(User $who, ChatThread $thread): \DOMXPath
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $this->actingAs($who)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent());
        libxml_clear_errors();

        return new \DOMXPath($dom);
    }

    private function items(\DOMXPath $x, string $class): int
    {
        return $x->query('//*[@id="chatList"]//button[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]')->length;
    }

    public function test_the_menu_offers_edit_only_on_a_persons_own_messages_and_only_while_it_is_open(): void
    {
        [$thread, $writer, , $owner] = $this->talk();
        $staff = User::factory()->create(['role' => 'it_support']);

        $writerPage = $this->page($writer, $thread);
        $this->assertSame(1, $this->items($writerPage, 'chat-msg-edit'), 'only their own');
        $this->assertSame(1, $this->items($writerPage, 'chat-msg-delete'));
        $this->assertSame(1, $writerPage->query('//*[@id="chatList"]//*[contains(@class, "chat-msg-menu-wrap")]')->length, 'the dots on their own message only');

        $this->assertSame(1, $this->items($this->page($owner, $thread), 'chat-msg-edit'), 'the owner has one message of their own');

        $staffPage = $this->page($staff, $thread);
        $this->assertSame(0, $this->items($staffPage, 'chat-msg-edit'), 'a moderator does not edit others');
        $this->assertSame(2, $this->items($staffPage, 'chat-msg-delete'), 'but deletes any');

        $this->assertSame(0, $this->items($this->page(User::factory()->create(['role' => 'supervisor']), $thread), 'chat-msg-edit'));

        $thread->forceFill(['is_locked' => true])->save();
        $lockedWriter = $this->page($writer, $thread);
        $this->assertSame(0, $this->items($lockedWriter, 'chat-msg-edit'));
        $this->assertSame(0, $this->items($lockedWriter, 'chat-msg-delete'), 'an author cannot change a closed thread');
        $this->assertSame(2, $this->items($this->page($staff, $thread), 'chat-msg-delete'), 'a moderator can');
    }

    public function test_a_moderators_delete_survives_the_lock_and_everyone_elses_items_are_marked_for_it(): void
    {
        [$thread, $writer] = $this->talk();
        $staff = User::factory()->create(['role' => 'it_support']);

        $mod = $this->page($staff, $thread);
        $this->assertSame(2, $mod->query('//*[@id="chatList"]//button[contains(@class, "chat-msg-delete")][@data-when="always"]')->length);

        $author = $this->page($writer, $thread);
        $this->assertSame(1, $author->query('//*[@id="chatList"]//button[contains(@class, "chat-msg-delete")][@data-when="open"]')->length, 'an author\'s delete goes with the lock');
        $this->assertSame(1, $author->query('//*[@id="chatList"]//button[contains(@class, "chat-msg-edit")][@data-when="open"]')->length);
    }

    public function test_the_page_says_a_message_was_edited(): void
    {
        [$thread, $writer, $mine, $owner] = $this->talk();

        $before = $this->page($owner, $thread);
        $this->assertSame(0, $before->query('//*[contains(@class, "msg-edited")]')->length);

        $this->change($writer, $thread, $mine, 'แก้แล้ว')->assertOk();

        $after = $this->page($owner, $thread);
        $this->assertSame(1, $after->query('//*[contains(@class, "msg-edited")]')->length);
        $this->assertStringContainsString('แก้ไขแล้ว', $after->query('//*[contains(@class, "msg-edited")]')->item(0)->textContent);
        $mine->delete();
        $this->assertSame(0, $this->page($owner, $thread)->query('//*[contains(@class, "msg-edited")]')->length, 'a deleted message carries no mark');
    }

    public function test_an_older_batch_is_loaded_with_the_marks_too(): void
    {
        Carbon::setTestNow();
        [$thread, $writer, $mine] = $this->talk();
        $this->change($writer, $thread, $mine, 'แก้แล้ว')->assertOk();

        $rows = $this->actingAs($writer)->getJson(route('chat.messages', $thread) . '?after_id=0')->assertOk()->json();
        $data = $rows['data'] ?? $rows;

        $this->assertTrue(collect($data)->firstWhere('id', $mine->id)['edited']);
    }
}
