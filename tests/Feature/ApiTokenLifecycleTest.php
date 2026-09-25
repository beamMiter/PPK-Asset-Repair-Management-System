<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * An API token used to work for ever, and `POST /api/auth/logout` answered 500 to a call made with the browser's own session
 * (it called `delete()` on a token type that has none).
 */
class ApiTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function bearer(string $plain): array
    {
        return ['Authorization' => 'Bearer ' . $plain];
    }

    public function test_a_token_expires_after_thirty_days(): void
    {
        $this->assertSame(43200, config('sanctum.expiration'));

        $user = User::factory()->create();
        $plain = $user->createToken('phone')->plainTextToken;

        $this->getJson('/api/auth/me', $this->bearer($plain))->assertOk();

        Carbon::setTestNow(now()->addMinutes(43200 - 1));
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/auth/me', $this->bearer($plain))->assertOk();

        Carbon::setTestNow(now()->addMinutes(2));
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/auth/me', $this->bearer($plain))->assertUnauthorized();
    }

    public function test_the_expiry_can_be_set_or_switched_off_from_the_environment_key(): void
    {
        $this->assertStringContainsString('SANCTUM_TOKEN_EXPIRATION_MINUTES', file_get_contents(config_path('sanctum.php')));
    }

    public function test_expired_tokens_are_tidied_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('sanctum:prune-expired')->assertSuccessful();
    }

    public function test_logging_out_with_a_bearer_token_ends_that_token_only(): void
    {
        $user = User::factory()->create();
        $phone = $user->createToken('phone')->plainTextToken;
        $user->createToken('laptop');

        $this->postJson('/api/auth/logout', [], $this->bearer($phone))->assertOk();

        $this->assertSame(['laptop'], PersonalAccessToken::pluck('name')->all());
    }

    public function test_logging_out_of_every_device_ends_every_token(): void
    {
        $user = User::factory()->create();
        $phone = $user->createToken('phone')->plainTextToken;
        $user->createToken('laptop');

        $this->postJson('/api/auth/logout-all', [], $this->bearer($phone))->assertOk();

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_logging_out_with_the_browsers_session_no_longer_answers_500(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJson(['message' => 'ออกจากระบบเรียบร้อยแล้ว']);
    }

    public function test_a_same_site_call_with_a_session_ends_that_session(): void
    {
        $user = User::factory()->create();

        // the Referer names a host Sanctum treats as "our own front end", so the request is given a session
        $this->actingAs($user, 'web')
            ->postJson('/api/auth/logout', [], ['Referer' => 'http://localhost/dashboard'])
            ->assertOk();

        $this->assertGuest('web');
    }
}
