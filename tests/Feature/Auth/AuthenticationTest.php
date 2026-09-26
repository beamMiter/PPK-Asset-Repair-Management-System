<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'citizen_id' => $user->citizen_id,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/dashboard'); // a member (the factory default) lands on the dashboard
    }

    public function test_staff_land_on_my_jobs_after_login(): void
    {
        $tech = User::factory()->create(['role' => 'it_support']);

        $this->post('/login', ['citizen_id' => $tech->citizen_id, 'password' => 'password'])->assertRedirect('/repair/my-jobs');

        $this->assertAuthenticatedAs($tech);
    }

    public function test_api_clients_get_204_from_login_and_logout(): void
    {
        $user = User::factory()->create();

        $this->postJson('/login', ['citizen_id' => $user->citizen_id, 'password' => 'password'])->assertNoContent();
        $this->assertAuthenticated();

        $this->postJson('/logout')->assertNoContent();
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'citizen_id' => $user->citizen_id,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
