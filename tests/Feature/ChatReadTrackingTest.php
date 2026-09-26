<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The web chat controller predated chat_thread_reads: it never advanced a
 * read pointer on send and hard-coded myUpdates' unread to 0, and it did
 * not broadcast from the API path. Both controllers now share
 * App\Traits\HandlesChatReads.
 */
class ChatReadTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatMessageSent::class]);
    }

    public function test_web_store_message_advances_the_senders_read_pointer(): void
    {
        $user   = User::factory()->create(['role' => 'it_support']);
        $thread = ChatThread::create(['title' => 'T', 'author_id' => $user->id, 'is_locked' => false]);

        $this->actingAs($user)
            ->post(route('chat.messages.store', $thread), ['body' => 'hello'])
            ->assertRedirect();

        $this->assertDatabaseHas('chat_thread_reads', [
            'user_id'        => $user->id,
            'chat_thread_id' => $thread->id,
        ]);
        $this->assertSame(
            (int) $thread->messages()->latest('id')->first()->id,
            (int) \DB::table('chat_thread_reads')->where('user_id', $user->id)->value('last_read_message_id'),
        );
    }

    public function test_opening_a_thread_marks_it_read(): void
    {
        $author = User::factory()->create(['role' => 'it_support']);
        $other  = User::factory()->create(['role' => 'it_support']);

        $thread = ChatThread::create(['title' => 'T', 'author_id' => $author->id, 'is_locked' => false]);
        $this->actingAs($other)->post(route('chat.messages.store', $thread), ['body' => 'new message']);

        // before opening it, the widget correctly shows it unread
        $before = collect($this->actingAs($author)->getJson(route('chat.my_updates'))->json())->firstWhere('id', $thread->id);
        $this->assertSame(1, $before['unread'], 'unread before opening the thread');

        // clicking into the thread from the widget is a GET to chat.index with its id — that visit should read it
        $this->actingAs($author)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk();

        $after = collect($this->actingAs($author)->getJson(route('chat.my_updates'))->json())->firstWhere('id', $thread->id);
        $this->assertSame(0, $after['unread'], 'still unread after opening it: the widget would keep alerting');
    }

    public function test_opening_a_thread_with_no_messages_does_not_error(): void
    {
        $author = User::factory()->create(['role' => 'it_support']);
        $thread = ChatThread::create(['title' => 'Empty', 'author_id' => $author->id, 'is_locked' => false]);

        $this->actingAs($author)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk();

        $this->assertDatabaseMissing('chat_thread_reads', ['chat_thread_id' => $thread->id]);
    }

    public function test_web_my_updates_reports_real_unread(): void
    {
        $author = User::factory()->create(['role' => 'it_support']);
        $other  = User::factory()->create(['role' => 'it_support']);

        $thread = ChatThread::create(['title' => 'T', 'author_id' => $author->id, 'is_locked' => false]);

        // author posts (their pointer moves to msg1)
        $this->actingAs($author)->post(route('chat.messages.store', $thread), ['body' => 'first']);
        // someone else posts msg2
        $this->actingAs($other)->post(route('chat.messages.store', $thread), ['body' => 'second']);

        $items = $this->actingAs($author)
            ->getJson(route('chat.my_updates'))
            ->assertOk()
            ->json();

        $row = collect($items)->firstWhere('id', $thread->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['unread']);
    }

    public function test_api_store_message_broadcasts(): void
    {
        $user   = User::factory()->create(['role' => 'it_support']);
        $thread = ChatThread::create(['title' => 'T', 'author_id' => $user->id, 'is_locked' => false]);

        Sanctum::actingAs($user);
        $this->postJson("/api/threads/{$thread->id}/messages", ['body' => 'hi'])
            ->assertCreated();

        Event::assertDispatched(ChatMessageSent::class);
    }

    public function test_a_member_who_did_not_start_the_thread_cannot_lock_it_on_either_path(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $owner  = User::factory()->create(['role' => 'it_support']);
        $thread = ChatThread::create(['title' => 'T', 'author_id' => $owner->id, 'is_locked' => false]);

        $this->actingAs($member)
            ->post(route('chat.lock', $thread))
            ->assertForbidden();

        Sanctum::actingAs($member);
        $this->postJson("/api/threads/{$thread->id}/lock")->assertForbidden();
    }
}
