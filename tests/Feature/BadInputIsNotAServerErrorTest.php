<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Two inputs a person can send that made the app answer 500 instead of saying what is wrong. Both were found by sending every
 * route that changes something an empty, a junk and a list-valued payload, with an id that exists, one that does not and one that
 * is text (see MutationRoutesNeverAnswerWithAServerErrorTest, which keeps that sweep going).
 */
class BadInputIsNotAServerErrorTest extends TestCase
{
    use RefreshDatabase;

    private function password(array $overrides = []): array
    {
        return array_merge(['current_password' => 'whatever', 'password' => 'Newpass123', 'password_confirmation' => 'Newpass123'], $overrides);
    }

    public function test_a_current_password_sent_as_a_list_is_refused_on_the_profile_form(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($user)->from('/profile/edit')
            ->put('/password', $this->password(['current_password' => ['x']]))
            ->assertRedirect('/profile/edit')
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');
    }

    public function test_a_current_password_sent_as_a_list_is_a_422_for_the_app(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'member']));

        $this->putJson('/api/auth/password', $this->password(['current_password' => ['x']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_a_maintenance_type_id_that_is_not_a_number_is_a_404_not_a_server_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/settings/maintenance-types/abc/edit')->assertNotFound();
        $this->actingAs($admin)->put('/settings/maintenance-types/abc', ['name' => 'x'])->assertNotFound();
        $this->actingAs($admin)->delete('/settings/maintenance-types/abc')->assertNotFound();
    }

    public function test_a_maintenance_type_id_that_does_not_exist_is_still_a_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/settings/maintenance-types/999999/edit')->assertNotFound();
    }
}
