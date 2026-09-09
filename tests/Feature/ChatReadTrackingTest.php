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

    public function test_member_cannot_lock_a_thread_on_either_path(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $thread = ChatThread::create(['title' => 'T', 'author_id' => $member->id, 'is_locked' => false]);

        $this->actingAs($member)
            ->post(route('chat.lock', $thread))
            ->assertForbidden();

        Sanctum::actingAs($member);
        $this->postJson("/api/threads/{$thread->id}/lock")->assertForbidden();
    }
}
