<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Events\ChatThreadDeleted;
use App\Events\ChatThreadLockChanged;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The chat's channels were public: anybody holding the Pusher key (it is in the page's JavaScript) could subscribe to `chat.{id}` - the ids
 * count 1, 2, 3 - and read every message and name live without ever signing in. They are private channels now (OWASP WebSocket Security:
 * authenticate and authorise a subscription): the browser asks POST /broadcasting/auth first, and only a signed-in, active account that
 * is not waiting to change its password gets a signature.
 */
class ChatChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher', 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => '1',
                'options' => ['cluster' => 'mt1', 'useTLS' => true],
            ],
        ]);
    }

    private function thread(): ChatThread
    {
        $author = User::factory()->create(['role' => 'it_support']);

        return ChatThread::create(['title' => 'เครื่องพิมพ์ชั้น 2', 'author_id' => $author->id, 'is_locked' => false]);
    }

    private function subscribe(string $channel, ?string $token = null)
    {
        $request = $token ? $this->withToken($token) : $this;

        return $request->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel]);
    }

    public function test_a_signed_in_account_is_given_a_signature_for_a_thread(): void
    {
        $thread = $this->thread();

        foreach (['member', 'it_support', 'technician', 'supervisor', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));

            $this->subscribe('private-chat.' . $thread->id)->assertOk()->assertJsonStructure(['auth']);
        }
    }

    public function test_the_app_asks_with_its_bearer_token(): void
    {
        $thread = $this->thread();
        $token = User::factory()->create(['role' => 'member'])->createToken('phone')->plainTextToken;

        $this->subscribe('private-chat.' . $thread->id, $token)->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_nobody_who_is_not_signed_in_is_let_in(): void
    {
        $thread = $this->thread();

        $this->subscribe('private-chat.' . $thread->id)->assertUnauthorized();
        $this->subscribe('private-chat.' . $thread->id, 'not-a-real-token')->assertUnauthorized();
    }

    public function test_a_thread_that_does_not_exist_or_was_deleted_has_no_listeners(): void
    {
        $gone = $this->thread();
        $gone->delete();
        $this->actingAs(User::factory()->create(['role' => 'member']));

        $this->subscribe('private-chat.999999')->assertForbidden();
        $this->subscribe('private-chat.' . $gone->id)->assertForbidden();
    }

    public function test_a_suspended_account_and_one_waiting_to_change_its_password_are_stopped(): void
    {
        $thread = $this->thread();

        $this->actingAs(User::factory()->create(['role' => 'member', 'suspended_at' => now()]));
        $this->subscribe('private-chat.' . $thread->id)->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'member', 'must_change_password' => true]));
        $this->subscribe('private-chat.' . $thread->id)->assertForbidden()->assertJsonPath('code', 'password_change_required');
    }

    public function test_another_persons_own_channel_and_an_unknown_channel_stay_closed(): void
    {
        $me = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'member']);
        $this->actingAs($me);

        $this->subscribe('private-App.Models.User.' . $other->id)->assertForbidden();
        $this->subscribe('private-chat-secret.1')->assertForbidden();
        $this->subscribe('private-chat.')->assertForbidden();
    }

    public function test_every_chat_event_is_announced_on_a_private_channel(): void
    {
        $thread = $this->thread();
        $message = ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $thread->author_id, 'body' => 'x']);

        foreach ([new ChatMessageSent($message), new ChatThreadLockChanged($thread->id, true), new ChatThreadDeleted($thread->id)] as $event) {
            $channels = $event->broadcastOn();

            $this->assertNotEmpty($channels);
            foreach ($channels as $channel) {
                $this->assertInstanceOf(PrivateChannel::class, $channel, $event::class . ' would be readable without signing in');
                $this->assertSame('private-chat.' . $thread->id, $channel->name);
            }
        }
    }

    public function test_the_page_listens_on_a_private_channel(): void
    {
        $js = file_get_contents(resource_path('js/chat/page.js'));

        $this->assertStringContainsString('win.Echo.private(channel)', $js);
        $this->assertStringNotContainsString('win.Echo.channel(', $js);
    }
}
