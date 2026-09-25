<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ActiveLogins;
use App\Services\PasswordRecovery;
use App\Support\PasswordResetMessage;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * "Forgot my password" must not tell whoever asks which e-mail addresses have an account, and a reset must end every other way
 * into the account — a password is reset because someone else may have it.
 */
class PasswordRecoverySecurityTest extends TestCase
{
    use RefreshDatabase;

    private function reset(string $email, string $token, string $password = 'NewPassw0rd!')
    {
        return $this->from('/reset-password/x')->post('/reset-password', [
            'token' => $token, 'email' => $email, 'password' => $password, 'password_confirmation' => $password,
        ]);
    }

    private function sessionRow(string $id, ?int $userId): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
    }

    // ---- asking for a link -----------------------------------------------------------------------------------------

    public function test_the_browser_hears_the_same_thing_for_an_address_with_an_account_and_one_without(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
        $known = [session('status'), session('errors')?->all()];

        $this->flushSession();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.test']);
        $unknown = [session('status'), session('errors')?->all()];

        $this->assertSame($known, $unknown);
        $this->assertSame(PasswordResetMessage::linkRequested(), $unknown[0]);
    }

    public function test_the_api_hears_the_same_thing_too(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->postJson('/api/auth/password/email', ['email' => $user->email]);
        $unknown = $this->postJson('/api/auth/password/email', ['email' => 'nobody@example.test']);

        $known->assertOk();
        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->json(), $unknown->json());
    }

    public function test_asking_twice_in_a_row_does_not_reveal_the_account_either(): void
    {
        // the broker refuses a second link within a minute — but only for an address that has an account
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', PasswordResetMessage::linkRequested());
    }

    public function test_only_an_address_with_an_account_is_mailed(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => 'nobody@example.test']);
        Notification::assertNothingSent();

        $this->post('/forgot-password', ['email' => $user->email]);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_the_mail_goes_out_after_the_answer_so_the_time_it_takes_says_nothing(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        PasswordRecovery::requestLink($user->email);
        Notification::assertNothingSent();          // not while the person is still waiting for their answer

        $this->app->terminate();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_a_broken_mail_server_is_reported_to_us_not_shown_to_them(): void
    {
        Exceptions::fake();
        Password::shouldReceive('sendResetLink')->andThrow(new \RuntimeException('smtp is down'));

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'anyone@example.test'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', PasswordResetMessage::linkRequested());

        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'smtp is down');
    }

    // ---- setting the new password ----------------------------------------------------------------------------------

    public function test_a_wrong_token_and_an_unknown_address_are_refused_in_the_same_words(): void
    {
        $user = User::factory()->create();

        $this->reset($user->email, 'not-a-real-token');
        $wrongToken = session('errors')->first('email');

        $this->flushSession();
        $this->reset('nobody@example.test', 'not-a-real-token');
        $unknownAddress = session('errors')->first('email');

        $this->assertSame($wrongToken, $unknownAddress);
        $this->assertSame(PasswordResetMessage::resetRefused(Password::INVALID_TOKEN), $wrongToken);

        $api = $this->postJson('/api/auth/password/reset', ['token' => 'x', 'email' => 'nobody@example.test', 'password' => 'NewPassw0rd!', 'password_confirmation' => 'NewPassw0rd!']);
        $api->assertStatus(400)->assertJson(['code' => 'password_reset_failed', 'message' => $wrongToken]);
    }

    public function test_a_reset_ends_every_other_way_into_the_account(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->createToken('phone');
        $other->createToken('phone');
        $this->sessionRow('attackers-browser', $user->id);
        $this->sessionRow('someone-elses', $other->id);
        $rememberBefore = $user->fresh()->remember_token;

        $this->reset($user->email, Password::createToken($user))->assertSessionHasNoErrors();

        $this->assertSame(0, $user->tokens()->count(), 'API tokens');
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count(), 'browser sessions');
        $this->assertNotSame($rememberBefore, $user->fresh()->remember_token, '"remember me" cookies');
        $this->assertSame(1, $other->tokens()->count(), 'another user is untouched');
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertTrue(Hash::check('NewPassw0rd!', $user->fresh()->password));
    }

    public function test_the_api_reset_ends_them_too_and_the_password_works(): void
    {
        $user = User::factory()->create();
        $user->createToken('phone');

        $this->postJson('/api/auth/password/reset', [
            'token' => Password::createToken($user), 'email' => $user->email,
            'password' => 'NewPassw0rd!', 'password_confirmation' => 'NewPassw0rd!',
        ])->assertOk()->assertJson(['message' => PasswordResetMessage::resetDone()]);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(Hash::check('NewPassw0rd!', $user->fresh()->password));
    }

    public function test_changing_your_own_password_keeps_this_device_and_ends_the_rest(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $user->createToken('phone');
        $this->startSession();
        $here = session()->getId();
        $this->sessionRow($here, $user->id);
        $this->sessionRow('another-device', $user->id);

        // the request must arrive on the session that is "this device": the test client does not send the cookie by itself
        $this->actingAs($user)->withCookie(config('session.cookie'), $here)->from('/profile')->put('/password', [
            'current_password' => 'password', 'password' => 'New-password1', 'password_confirmation' => 'New-password1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame([$here], DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all());
    }

    public function test_without_database_sessions_only_the_tokens_and_remember_cookies_are_ended(): void
    {
        $user = User::factory()->create();
        $user->createToken('phone');

        $this->assertNull(ActiveLogins::endAll($user));

        $this->assertSame(0, $user->tokens()->count());
    }

    // ---- one rule for the password a person chooses ----------------------------------------------------------------

    public function test_a_password_of_only_letters_or_only_digits_is_refused_everywhere_in_thai(): void
    {
        foreach (['onlyletters', '1234567890'] as $weak) {
            $this->from('/register')->post('/register', [
                'name' => 'Somchai', 'citizen_id' => '1234567890123', 'password' => $weak, 'password_confirmation' => $weak,
            ])->assertSessionHasErrors('password');
            $this->assertStringContainsString('รหัสผ่าน', session('errors')->first('password'));

            $this->postJson('/api/auth/password/reset', ['token' => 'x', 'email' => 'a@example.test', 'password' => $weak, 'password_confirmation' => $weak])
                ->assertStatus(422)->assertJsonValidationErrors('password');
        }

        $this->from('/register')->post('/register', [
            'name' => 'Somchai', 'citizen_id' => '1234567890123', 'password' => 'Password123', 'password_confirmation' => 'Password123',
        ])->assertSessionHasNoErrors();
    }

    public function test_the_forms_say_what_a_password_needs(): void
    {
        $this->get('/register')->assertSee('อย่างน้อย 8 ตัวอักษร ต้องมีทั้งตัวอักษรและตัวเลข');
        $this->get('/reset-password/sometoken?email=a@example.test')->assertSee('อย่างน้อย 8 ตัวอักษร ต้องมีทั้งตัวอักษรและตัวเลข');
    }
}
