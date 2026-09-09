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
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // In the testing environment the controller returns 204 No Content.
        $response->assertNoContent();
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'citizen_id' => '1234567890123',
            'email' => 'test@example.com',
        ]);
    }

    public function test_registration_requires_a_thirteen_digit_citizen_id(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'citizen_id' => '123',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('citizen_id');
        $this->assertGuest();
    }
}
