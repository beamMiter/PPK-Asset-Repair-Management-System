<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'citizen_id' => '1234567890123',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'citizen_id' => '1234567890123',
            'email' => 'test@example.com',
        ]);
    }

    public function test_api_clients_get_204_from_registration(): void
    {
        $this->postJson('/register', [
            'name' => 'Api User',
            'citizen_id' => '3210987654321',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertNoContent();

        $this->assertAuthenticated();
    }

    public function test_registration_requires_a_thirteen_digit_citizen_id(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'citizen_id' => '123',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('citizen_id');
        $this->assertGuest();
    }
}
