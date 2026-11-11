<?php

namespace Tests\Feature;

use App\Events\MaintenanceRequestCreated;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A new repair request was announced on the public channel `maintenance-requests`, with its number and title: anybody holding
 * the Pusher key (it is in the page's JavaScript) could read every new request live without signing in. It is a private
 * channel now, like the chat's: only the staff who get the new-job bell (the `view-my-jobs` gate) are given a signature.
 */
class NewJobChannelIsPrivateTest extends TestCase
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

    private function subscribe()
    {
        return $this->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-maintenance-requests']);
    }

    public function test_a_new_request_is_announced_on_a_private_channel(): void
    {
        $channels = Arr::wrap((new MaintenanceRequestCreated(['id' => 1, 'title' => 'จอเสีย']))->broadcastOn());

        $this->assertNotEmpty($channels);
        foreach ($channels as $channel) {
            $this->assertInstanceOf(PrivateChannel::class, $channel, 'a new request would be readable without signing in');
            $this->assertSame('private-maintenance-requests', $channel->name);
        }
    }

    public static function staffRoles(): array
    {
        return array_map(fn ($role) => [$role], array_merge(['admin', 'supervisor'], User::workerRoles()));
    }

    #[DataProvider('staffRoles')]
    public function test_the_staff_may_listen(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));

        $this->subscribe()->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_a_member_and_somebody_signed_out_may_not(): void
    {
        $this->subscribe()->assertUnauthorized();

        $this->actingAs(User::factory()->create(['role' => 'member']));
        $this->subscribe()->assertForbidden();
    }

    public function test_the_bell_listens_on_the_private_channel(): void
    {
        $js = file_get_contents(resource_path('js/repair/my-jobs.js'));

        $this->assertStringContainsString('window.Echo.private("maintenance-requests")', $js);
        $this->assertStringNotContainsString('Echo.channel("maintenance-requests")', $js);
    }
}
