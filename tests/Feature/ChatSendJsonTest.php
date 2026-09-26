<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The chat page sends a message with fetch (no reload) and draws it from the answer: the JSON the server gives back is the message,
 * with who wrote it. A plain form post - no JavaScript - still goes back to the page.
 */
class ChatSendJsonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatMessageSent::class]);
    }

    private function open(User $author): ChatThread
    {
        return ChatThread::create(['title' => 'เครื่องพิมพ์ชั้น 2', 'author_id' => $author->id, 'is_locked' => false]);
    }

    public function test_a_json_post_answers_201_with_the_message_and_who_wrote_it(): void
    {
        $me = User::factory()->create(['role' => 'member', 'name' => 'สมชาย ใจดี']);
        $thread = $this->open($me);

        $res = $this->actingAs($me)->postJson(route('chat.messages.store', $thread), ['body' => "สวัสดี\nครับ"])->assertCreated();

        $res->assertJsonPath('body', "สวัสดี\nครับ")
            ->assertJsonPath('user_id', $me->id)
            ->assertJsonPath('chat_thread_id', $thread->id)
            ->assertJsonPath('user.name', 'สมชาย ใจดี');
        $this->assertNotNull($res->json('id'));
        $this->assertNotNull($res->json('user.avatar_thumb_url'), 'the row the page draws has an avatar to show');
        $this->assertDatabaseHas('chat_messages', ['id' => $res->json('id'), 'body' => "สวัสดี\nครับ"]);
    }

    public function test_it_still_moves_the_read_pointer_and_broadcasts(): void
    {
        $me = User::factory()->create(['role' => 'member']);
        $thread = $this->open($me);

        $id = $this->actingAs($me)->postJson(route('chat.messages.store', $thread), ['body' => 'x'])->json('id');

        $this->assertSame($id, (int) DB::table('chat_thread_reads')->where('user_id', $me->id)->value('last_read_message_id'));
        Event::assertDispatched(ChatMessageSent::class);
    }

    public function test_a_plain_form_post_still_goes_back_to_the_page(): void
    {
        $me = User::factory()->create(['role' => 'member']);
        $thread = $this->open($me);

        $this->actingAs($me)->from(route('chat.index', ['thread_id' => $thread->id]))
            ->post(route('chat.messages.store', $thread), ['body' => 'x'])
            ->assertRedirect(route('chat.index', ['thread_id' => $thread->id]));

        $this->assertSame(1, $thread->messages()->count());
    }

    public function test_an_empty_or_too_long_message_is_a_422_and_nothing_is_saved(): void
    {
        $me = User::factory()->create(['role' => 'member']);
        $thread = $this->open($me);

        $this->actingAs($me)->postJson(route('chat.messages.store', $thread), ['body' => ''])->assertStatus(422)->assertJsonValidationErrors('body');
        $this->actingAs($me)->postJson(route('chat.messages.store', $thread), ['body' => str_repeat('ก', 3001)])->assertStatus(422);

        $this->assertSame(0, $thread->messages()->count());
    }

    public function test_the_page_wires_the_composer_to_the_thread_s_address(): void
    {
        $me = User::factory()->create(['role' => 'member']);
        $thread = $this->open($me);

        $html = $this->actingAs($me)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();

        $this->assertStringContainsString('action="' . route('chat.messages.store', $thread) . '"', $html, 'what page.js posts to');
        $this->assertStringContainsString('id="chatForm"', $html);
        $this->assertStringContainsString('<meta name="csrf-token"', $html, 'and the token it sends');
    }
}
