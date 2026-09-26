<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A message is sent with an id the page makes for that attempt. If the connection drops after the server saved the message and before the
 * answer arrived, the page sends it again with the same id and gets the message it already has: it is never saved (or announced) twice.
 */
class ChatIdempotentSendTest extends TestCase
{
    use RefreshDatabase;

    private User $me;
    private ChatThread $thread;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatMessageSent::class]);
        $this->me = User::factory()->create(['role' => 'member']);
        $this->thread = ChatThread::create(['title' => 'x', 'author_id' => $this->me->id, 'is_locked' => false]);
    }

    private function send(string $body, ?string $clientId, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->me)->postJson(route('chat.messages.store', $this->thread), array_filter(['body' => $body, 'client_id' => $clientId], fn ($v) => $v !== null));
    }

    public function test_the_same_attempt_twice_saves_one_message_and_announces_it_once(): void
    {
        $id = (string) Str::uuid();

        $first = $this->send('ครั้งเดียว', $id)->assertCreated();
        $again = $this->send('ครั้งเดียว', $id)->assertOk();

        $this->assertSame($first->json('id'), $again->json('id'), 'the message it already has');
        $this->assertSame(1, $this->thread->messages()->count());
        Event::assertDispatchedTimes(ChatMessageSent::class, 1);
        $this->assertSame($id, ChatMessage::firstOrFail()->client_uuid);
    }

    public function test_a_new_id_is_a_new_message_even_with_the_same_words(): void
    {
        $this->send('เหมือนกัน', (string) Str::uuid())->assertCreated();
        $this->send('เหมือนกัน', (string) Str::uuid())->assertCreated();

        $this->assertSame(2, $this->thread->messages()->count());
    }

    public function test_a_send_with_no_id_or_a_bad_one_works_as_before(): void
    {
        $this->send('ไม่มีรหัส', null)->assertCreated();
        $this->send('ไม่มีรหัส', null)->assertCreated();
        $this->actingAs($this->me)->postJson(route('chat.messages.store', $this->thread), ['body' => 'รหัสผิด', 'client_id' => 'not-a-uuid'])->assertStatus(422);

        $this->assertSame(2, $this->thread->messages()->count());
        $this->assertNull(ChatMessage::first()->client_uuid);
    }

    public function test_the_id_belongs_to_the_sender(): void
    {
        $id = (string) Str::uuid();
        $other = User::factory()->create(['role' => 'member']);

        $this->send('ของผม', $id)->assertCreated();
        $this->send('ของเขา', $id, $other)->assertCreated();

        $this->assertSame(2, $this->thread->messages()->count());
    }

    public function test_a_retry_is_answered_even_if_the_thread_was_locked_in_between(): void
    {
        $id = (string) Str::uuid();
        $this->send('ทันก่อนล็อก', $id)->assertCreated();
        $this->thread->update(['is_locked' => true]);

        $this->send('ทันก่อนล็อก', $id)->assertOk();          // it was accepted when it was sent
        $this->send('ข้อความใหม่', (string) Str::uuid())->assertForbidden();

        $this->assertSame(1, $this->thread->messages()->count());
    }

    public function test_an_id_from_another_thread_is_not_a_retry_here(): void
    {
        $id = (string) Str::uuid();
        $this->send('ห้องแรก', $id)->assertCreated();
        $second = ChatThread::create(['title' => 'y', 'author_id' => $this->me->id, 'is_locked' => false]);

        $res = $this->actingAs($this->me)->postJson(route('chat.messages.store', $second), ['body' => 'ห้องสอง', 'client_id' => $id]);

        $res->assertCreated();
        $this->assertNotSame(ChatMessage::where('body', 'ห้องแรก')->value('id'), $res->json('id'), 'not the other thread\'s message');
        $this->assertSame(1, $this->thread->messages()->count());
        $this->assertSame(1, $second->messages()->count());
    }

    public function test_the_database_refuses_the_same_id_from_the_same_sender_twice(): void
    {
        $id = (string) Str::uuid();
        ChatMessage::create(['chat_thread_id' => $this->thread->id, 'user_id' => $this->me->id, 'body' => 'a', 'client_uuid' => $id]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        ChatMessage::create(['chat_thread_id' => $this->thread->id, 'user_id' => $this->me->id, 'body' => 'b', 'client_uuid' => $id]);
    }

    public function test_the_api_has_the_same_retry_answer(): void
    {
        Sanctum::actingAs($this->me);
        $id = (string) Str::uuid();

        $first = $this->postJson("/api/threads/{$this->thread->id}/messages", ['body' => 'แอป', 'client_id' => $id])->assertCreated();
        $again = $this->postJson("/api/threads/{$this->thread->id}/messages", ['body' => 'แอป', 'client_id' => $id])->assertOk();

        $this->assertSame($first->json('id'), $again->json('id'));
        $this->assertSame(1, $this->thread->messages()->count());
    }
}
