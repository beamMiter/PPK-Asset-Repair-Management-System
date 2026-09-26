<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * The browser flow (forgot-password page → e-mailed link → reset page) was broken end to end: both POST handlers
 * answered with raw JSON, and the e-mailed link pointed at `config('app.frontend_url')/password-reset/…`, a setting
 * that does not exist and a path this app does not serve.
 */
class WebPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_link_returns_to_the_form_with_a_message_not_json(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $res = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);

        $res->assertRedirect('/forgot-password');
        $res->assertSessionHas('status');
        $res->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_the_emailed_link_opens_this_apps_reset_page(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            $this->assertStringStartsWith(url('/reset-password/'), $url);
            $this->get($url)->assertOk()->assertSee('name="token"', false);

            return true;
        });
    }

    public function test_setting_a_new_password_redirects_to_login_and_the_password_works(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $res = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassw0rd!',
            'password_confirmation' => 'NewPassw0rd!',
        ]);

        $res->assertRedirect(route('login'));
        $res->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPassw0rd!', $user->fresh()->password));
    }

    public function test_a_bad_token_goes_back_with_an_error_on_the_email_field(): void
    {
        $user = User::factory()->create();
        $before = $user->password;

        $res = $this->from('/reset-password/x')->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'NewPassw0rd!',
            'password_confirmation' => 'NewPassw0rd!',
        ]);

        $res->assertRedirect('/reset-password/x');
        $res->assertSessionHasErrors('email');
        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_an_unknown_email_gets_the_same_answer_as_a_known_one_and_it_points_to_the_admin_for_accounts_without_one(): void
    {
        Notification::fake();

        $res = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.test']);

        $res->assertRedirect('/forgot-password');
        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('status');
        $this->assertStringContainsString('ผู้ดูแลระบบ', session('status'));
        Notification::assertNothingSent();
    }

    public function test_json_clients_still_get_json(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk()->assertJsonStructure(['status']);
        $unknown = $this->postJson('/forgot-password', ['email' => 'nobody@example.test'])->assertOk();

        $this->assertSame($known->json(), $unknown->json());
        $this->postJson('/forgot-password', ['email' => 'not-an-address'])->assertStatus(422);   // a malformed address says nothing about accounts
    }
}
