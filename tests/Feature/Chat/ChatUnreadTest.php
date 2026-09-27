<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatUnreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_counts_reflect_new_messages_and_clear_when_user_posts()
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        // Bob creates a thread and posts 3 messages
        $thread = ChatThread::create(['title' => 'Hello', 'author_id' => $bob->id]);
        ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $bob->id, 'body' => 'one']);
        ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $bob->id, 'body' => 'two']);
        ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $bob->id, 'body' => 'three']);

        // Alice views threads list -> should see unread_count = 3
        Sanctum::actingAs($alice);
        $resp = $this->getJson('/api/threads');
        $resp->assertOk();
        $data = collect($resp->json('data'));
        $threadRow = $data->firstWhere('id', $thread->id);
        $this->assertNotNull($threadRow);
        $this->assertSame(3, $threadRow['unread_count']);

        // Alice posts a message -> her read marker should be updated and unread_count becomes 0
        $post = $this->postJson("/api/threads/{$thread->id}/messages", ['body' => 'reply from alice']);
        $post->assertStatus(201);

        $resp2 = $this->getJson('/api/threads');
        $resp2->assertOk();
        $data2 = collect($resp2->json('data'));
        $threadRow2 = $data2->firstWhere('id', $thread->id);
        $this->assertSame(0, $threadRow2['unread_count']);
    }
}
