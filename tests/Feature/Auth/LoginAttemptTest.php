<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\LoginAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * One sign-in check for the browser form and the API (App\Services\LoginAttempt), with two counters of wrong tries: one per
 * account and address, one per address. The second is what stops "password spraying" — one likely password tried against
 * every citizen id from one machine, which never trips a per-account counter.
 */
class LoginAttemptTest extends TestCase
{
    use RefreshDatabase;

    private function citizenId(int $n): string
    {
        return str_pad((string) $n, 13, '0', STR_PAD_LEFT);
    }

    /** $n wrong tries, each against a different citizen id, all from this test's one address. */
    private function spray(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            $this->from('/login')->post('/login', ['citizen_id' => $this->citizenId($i), 'password' => 'Summer2026']);
        }
    }

    public function test_spraying_one_password_at_many_accounts_from_one_address_is_stopped(): void
    {
        $victim = User::factory()->create(['citizen_id' => $this->citizenId(999)]);

        $this->spray(LoginAttempt::MAX_PER_ADDRESS);

        // the 31st try from this address is refused — even with the RIGHT password for a real account
        $this->from('/login')->post('/login', ['citizen_id' => $victim->citizen_id, 'password' => 'password'])
            ->assertSessionHasErrors('citizen_id');
        $this->assertStringContainsString('กรุณารอ', session('errors')->first('citizen_id'));
        $this->assertGuest();
    }

    public function test_below_the_limit_a_right_password_still_signs_in(): void
    {
        $user = User::factory()->create(['citizen_id' => $this->citizenId(999)]);

        $this->spray(LoginAttempt::MAX_PER_ADDRESS - 1);

        $this->post('/login', ['citizen_id' => $user->citizen_id, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_right_password_does_not_clear_the_per_address_count(): void
    {
        $user = User::factory()->create(['citizen_id' => $this->citizenId(999)]);

        $this->spray(LoginAttempt::MAX_PER_ADDRESS - 1);
        $this->post('/login', ['citizen_id' => $user->citizen_id, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
        Auth::guard('web')->logout();

        // one more wrong try makes 30 — the success in between did not wipe the 29 before it
        $this->post('/login', ['citizen_id' => $this->citizenId(500), 'password' => 'nope']);
        $this->from('/login')->post('/login', ['citizen_id' => $user->citizen_id, 'password' => 'password'])
            ->assertSessionHasErrors('citizen_id');
        $this->assertGuest();
    }

    public function test_a_ward_signing_in_behind_one_address_is_not_locked_out_by_its_own_successes(): void
    {
        $users = collect(range(1, 12))->map(fn ($n) => User::factory()->create(['citizen_id' => $this->citizenId($n)]));

        foreach ($users as $user) {
            $this->post('/login', ['citizen_id' => $user->citizen_id, 'password' => 'password']);
            $this->assertAuthenticatedAs($user);
            Auth::guard('web')->logout();
        }

        $this->from('/login')->post('/login', ['citizen_id' => $this->citizenId(1), 'password' => 'wrong']);
        $this->assertSame(LoginAttempt::WRONG, session('errors')->first('citizen_id'), 'a wrong password is still just "wrong", not a lock-out');
    }

    public function test_the_api_login_shares_the_address_counter_and_says_how_long_to_wait(): void
    {
        $victim = User::factory()->create(['citizen_id' => $this->citizenId(999)]);
        for ($i = 1; $i <= LoginAttempt::MAX_PER_ADDRESS; $i++) {
            LoginAttempt::for($this->citizenId($i), '127.0.0.1')->fail();
        }

        $res = $this->postJson('/api/auth/login', ['citizen_id' => $victim->citizen_id, 'password' => 'password']);

        $res->assertStatus(429)->assertJson(['code' => 'too_many_attempts']);
        $this->assertGreaterThan(0, (int) $res->headers->get('Retry-After'));
        $this->assertStringContainsString('กรุณารอ', $res->json('message'));
    }

    public function test_the_api_and_the_browser_word_a_wrong_login_the_same_way(): void
    {
        $user = User::factory()->create();

        $api = $this->postJson('/api/auth/login', ['citizen_id' => $user->citizen_id, 'password' => 'wrong-one'])
            ->assertStatus(401)->assertJson(['code' => 'invalid_credentials']);
        $this->from('/login')->post('/login', ['citizen_id' => $user->citizen_id, 'password' => 'wrong-one']);

        $this->assertSame(LoginAttempt::WRONG, $api->json('message'));
        $this->assertSame(LoginAttempt::WRONG, session('errors')->first('citizen_id'));
    }

    public function test_the_api_no_longer_treats_a_short_password_differently_from_any_other_wrong_one(): void
    {
        // it used to be refused with a 422 before the credentials were looked at (min:6) — the browser form never did
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', ['citizen_id' => $user->citizen_id, 'password' => 'abc'])->assertStatus(401);
    }

    public function test_an_unknown_citizen_id_costs_one_hash_check_like_a_known_one(): void
    {
        // a missing account answered faster, and the time said which citizen ids have one
        Hash::shouldReceive('make')->once()->andReturn('a-dummy-hash');
        Hash::shouldReceive('check')->once()->with('secret-pass', 'a-dummy-hash')->andReturn(false);

        $this->assertNull(LoginAttempt::for($this->citizenId(4242), '127.0.0.1')->userWithPassword('secret-pass'));
    }

    public function test_the_dummy_hash_is_made_once_not_on_every_unknown_login(): void
    {
        LoginAttempt::for($this->citizenId(1), '127.0.0.1')->userWithPassword('x');

        Hash::shouldReceive('make')->never();
        Hash::shouldReceive('check')->once()->andReturn(false);
        LoginAttempt::for($this->citizenId(2), '127.0.0.1')->userWithPassword('x');

        $this->assertTrue(true);
    }

    public function test_a_password_stored_with_older_settings_is_rehashed_when_it_is_used(): void
    {
        $user = User::factory()->create(['citizen_id' => $this->citizenId(7)]);
        $old = $user->fresh()->password;
        Hash::driver('bcrypt')->setRounds(5);   // the factory's hash used the test setting (4)

        $found = LoginAttempt::for($user->citizen_id, '127.0.0.1')->userWithPassword('password');

        $this->assertNotNull($found);
        $this->assertNotSame($old, $user->fresh()->password);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_public_forms_have_a_ceiling_and_a_refused_browser_is_told_to_wait(): void
    {
        foreach (['login.store' => 'login', 'register.store' => 'register', 'password.email' => 'password-recovery', 'password.store' => 'password-recovery'] as $route => $limiter) {
            $this->assertContains("throttle:{$limiter}", Route::getRoutes()->getByName($route)->gatherMiddleware(), $route);
            $this->assertNotNull(RateLimiter::limiter($limiter), $limiter);
        }

        for ($i = 0; $i < 5; $i++) {
            $this->from('/register')->post('/register', [])->assertRedirect('/register');
        }

        $this->from('/register')->post('/register', ['name' => 'Kept Name'])
            ->assertRedirect('/register')
            ->assertSessionHas('toast.type', 'warning');
        $this->assertStringContainsString('ส่งคำขอถี่เกินไป', session('toast.message'));
        $this->assertSame('Kept Name', old('name'), 'what was typed comes back with the redirect');
    }

    public function test_a_refused_json_client_still_gets_its_429(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/forgot-password', ['email' => 'a@example.test']);
        }

        $this->postJson('/forgot-password', ['email' => 'a@example.test'])->assertStatus(429);
    }

    public function test_there_is_one_copy_of_the_sign_in_check(): void
    {
        foreach (['app/Http/Requests/Auth/LoginRequest.php', 'app/Http/Controllers/Api/AuthController.php'] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringNotContainsString('RateLimiter', $source, "$file counts tries itself again");
            $this->assertStringNotContainsString('Hash::check', $source, "$file checks a password itself again");
            $this->assertStringContainsString('LoginAttempt::for(', $source, $file);
        }
    }
}
