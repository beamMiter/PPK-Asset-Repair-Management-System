<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Events\MaintenanceRequestCreated;
use App\Models\Asset;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Real-time push (Pusher) is a nicety on top of a change that is already saved. When the push service is down or slow,
 * the user must still see their request created / their chat message posted — before, the failure surfaced *after*
 * the row was written: a warning toast with the raw cURL error for a request that had in fact been created (so the user
 * retried and made a duplicate), and a 500 for a chat message that had in fact been posted.
 */
class RealtimeFailureIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function pusherIsDown(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher', 'key' => 'k', 'secret' => 's', 'app_id' => '1',
                'options' => ['host' => '127.0.0.1', 'port' => 9, 'scheme' => 'http', 'useTLS' => false, 'timeout' => 2],
            ],
        ]);
    }

    private function asset(): Asset
    {
        return Asset::factory()->create(['asset_code' => 'RT-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null, 'status' => 'active']);
    }

    private function thread(User $author): ChatThread
    {
        return ChatThread::create(['title' => 'T', 'author_id' => $author->id, 'is_locked' => false]);
    }

    public function test_a_request_is_created_normally_while_the_push_service_is_down(): void
    {
        $this->pusherIsDown();
        $member = User::factory()->create(['role' => 'member']);

        $res = $this->actingAs($member)->post(route('maintenance.requests.store'), ['title' => 'Push is down', 'asset_id' => $this->asset()->id]);

        $req = MaintenanceRequest::where('title', 'Push is down')->firstOrFail();
        $res->assertRedirect(route('maintenance.requests.show', $req));
        $this->assertSame('success', session('toast.type'));
        $this->assertStringNotContainsStringIgnoringCase('pusher', (string) session('toast.message'));
        $this->assertStringNotContainsStringIgnoringCase('curl', (string) session('toast.message'));
        $this->assertSame(1, MaintenanceRequest::where('title', 'Push is down')->count());
    }

    public function test_the_api_answers_201_for_a_request_created_while_the_push_service_is_down(): void
    {
        $this->pusherIsDown();
        $member = User::factory()->create(['role' => 'member']);

        $res = $this->actingAs($member, 'sanctum')->postJson('/api/repair-requests', ['title' => 'API push down', 'asset_id' => $this->asset()->id]);

        $res->assertCreated();
        $this->assertSame('API push down', $res->json('data.title'));
    }

    public function test_a_chat_message_is_posted_normally_while_the_push_service_is_down(): void
    {
        $this->pusherIsDown();
        $user = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($user);

        $this->actingAs($user)->from('/chat')->post(route('chat.messages.store', $thread), ['body' => 'hello web'])->assertRedirect('/chat');
        $this->actingAs($user, 'sanctum')->postJson("/api/threads/{$thread->id}/messages", ['body' => 'hello api'])->assertCreated();

        $this->assertSame(['hello web', 'hello api'], ChatMessage::where('chat_thread_id', $thread->id)->orderBy('id')->pluck('body')->all());
    }

    public function test_the_failure_is_logged_for_whoever_runs_the_server(): void
    {
        $this->pusherIsDown();
        Log::spy();
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user, 'sanctum')->postJson("/api/threads/{$this->thread($user)->id}/messages", ['body' => 'x'])->assertCreated();

        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => str_contains($message, 'broadcast')
            && ($context['event'] ?? null) === ChatMessageSent::class
            && str_contains((string) ($context['error'] ?? ''), 'cURL'))->once();
    }

    public function test_a_hung_push_service_costs_at_most_its_timeout_and_the_message_is_still_posted(): void
    {
        // a listener that accepts the connection (the kernel does that for us) and never answers
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server, $errstr);
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher', 'key' => 'k', 'secret' => 's', 'app_id' => '1',
                'options' => ['host' => '127.0.0.1', 'port' => $port, 'scheme' => 'http', 'useTLS' => false, 'timeout' => 1],
                'client_options' => ['connect_timeout' => 1, 'timeout' => 1],
            ],
        ]);
        Log::spy();
        $user = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($user);

        $started = microtime(true);
        $this->actingAs($user, 'sanctum')->postJson("/api/threads/{$thread->id}/messages", ['body' => 'slow push'])->assertCreated();
        $seconds = microtime(true) - $started;
        fclose($server);

        $this->assertLessThan(5, $seconds, 'the save must not wait for a hung push service');
        $this->assertSame(1, ChatMessage::where('body', 'slow push')->count());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => str_contains($message, 'broadcast')
            && str_contains((string) ($context['error'] ?? ''), '28'))->once(); // cURL error 28: operation timed out
    }

    public function test_the_shipped_push_timeouts_are_short(): void
    {
        $pusher = config('broadcasting.connections.pusher');

        $this->assertLessThanOrEqual(5, $pusher['options']['timeout']);
        $this->assertLessThanOrEqual(3, $pusher['client_options']['connect_timeout']);
        $this->assertLessThanOrEqual(5, $pusher['client_options']['timeout']);
    }

    public function test_when_the_push_service_works_the_events_are_still_sent(): void
    {
        Event::fake([ChatMessageSent::class, MaintenanceRequestCreated::class]);
        $user = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($user);

        $this->actingAs($user)->post(route('chat.messages.store', $thread), ['body' => 'web']);
        $this->actingAs($user, 'sanctum')->postJson("/api/threads/{$thread->id}/messages", ['body' => 'api']);
        $this->actingAs($user)->post(route('maintenance.requests.store'), ['title' => 'Works', 'asset_id' => $this->asset()->id]);

        Event::assertDispatchedTimes(ChatMessageSent::class, 2);
        Event::assertDispatched(MaintenanceRequestCreated::class, fn ($e) => $e->data['title'] === 'Works');
    }
}
