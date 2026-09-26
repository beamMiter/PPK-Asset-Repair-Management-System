<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * `PUT /api/auth/password`: the app can change its own password. It is also the way out for a person an admin gave a password to -
 * before this, every API call answered 403 password_change_required and nothing on the API could clear it (the e-mail reset is no use to
 * somebody with no e-mail).
 */
class ApiChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, string $device = 'phone'): string
    {
        return $user->createToken($device)->plainTextToken;
    }

    private function change(string $token, array $body)
    {
        return $this->withToken($token)->putJson('/api/auth/password', $body);
    }

    private function forced(): User
    {
        return User::factory()->create(['role' => 'member', 'password' => Hash::make('OldPass123'), 'must_change_password' => true]);
    }

    public function test_a_person_an_admin_gave_a_password_to_is_locked_out_of_everything_but_this_and_logout(): void
    {
        $token = $this->login($this->forced());

        $this->withToken($token)->getJson('/api/user')->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->change($token, ['current_password' => 'wrong', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])->assertStatus(422);
    }

    public function test_changing_it_clears_the_flag_and_opens_the_api_again_on_the_same_token(): void
    {
        $user = $this->forced();
        $token = $this->login($user);

        $this->change($token, ['current_password' => 'OldPass123', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])
            ->assertOk()
            ->assertJsonPath('must_change_password', false);

        $user->refresh();
        $this->assertFalse((bool) $user->must_change_password);
        $this->assertTrue(Hash::check('NewPass456', $user->password));
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_every_other_token_of_the_account_ends(): void
    {
        $user = $this->forced();
        $this->login($user, 'old-laptop');
        $token = $this->login($user, 'phone');

        $this->change($token, ['current_password' => 'OldPass123', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])->assertOk();

        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame('phone', $user->tokens()->first()->name);
    }

    public function test_the_rules_are_the_same_as_the_profile_page(): void
    {
        $user = User::factory()->create(['role' => 'member', 'password' => Hash::make('OldPass123')]);
        $token = $this->login($user);

        $this->change($token, ['current_password' => 'OldPass123', 'password' => 'short1', 'password_confirmation' => 'short1'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->change($token, ['current_password' => 'OldPass123', 'password' => 'onlyletters', 'password_confirmation' => 'onlyletters'])->assertStatus(422);
        $this->change($token, ['current_password' => 'OldPass123', 'password' => 'NewPass456', 'password_confirmation' => 'different1'])->assertStatus(422);
        $this->change($token, ['current_password' => 'OldPass123', 'password' => 'OldPass123', 'password_confirmation' => 'OldPass123'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->change($token, ['password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('OldPass123', $user->fresh()->password), 'nothing changed');
    }

    public function test_an_account_with_no_flag_can_change_its_password_too(): void
    {
        $user = User::factory()->create(['role' => 'technician', 'password' => Hash::make('OldPass123')]);

        $this->change($this->login($user), ['current_password' => 'OldPass123', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])->assertOk();

        $this->assertTrue(Hash::check('NewPass456', $user->fresh()->password));
    }

    public function test_a_guest_cannot(): void
    {
        $this->putJson('/api/auth/password', ['current_password' => 'x', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])->assertUnauthorized();
    }

    public function test_the_attempts_are_limited(): void
    {
        $token = $this->login(User::factory()->create(['role' => 'member', 'password' => Hash::make('OldPass123')]));

        foreach (range(1, 6) as $i) {
            $this->change($token, ['current_password' => 'wrong', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])->assertStatus(422);
        }
        $this->change($token, ['current_password' => 'wrong', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])->assertStatus(429);
    }
}
