<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Deleting an account is a database-level operation, not a front-end feature: users are referenced by requests,
 * logs, assignments, ratings and chat, and a hard delete cascades into other people's data (chat threads with all
 * their messages, the ratings a person gave, job assignments). The delete route, action, buttons and the edit page's
 * "danger zone" are gone; editing a user stays.
 */
class UserDeletionRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_no_delete_route_or_action_left(): void
    {
        $this->assertFalse(Route::has('admin.users.destroy'));
        $this->assertFalse(method_exists(\App\Http\Controllers\Admin\UserController::class, 'destroy'));
    }

    public function test_even_an_admin_cannot_delete_a_user_over_http(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $victim = User::factory()->create(['role' => 'member']);

        $this->actingAs($admin)->delete('/admin/users/'.$victim->id)->assertStatus(405);

        $this->assertNotNull(User::find($victim->id), 'the account must still exist');
    }

    public function test_no_page_offers_a_delete_control(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'member']);

        foreach ([route('admin.users.index'), route('admin.users.edit', $other)] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('confirmDeleteUser', $html, $url);
            $this->assertStringNotContainsString('delete-user-form', $html, $url);
            $this->assertStringNotContainsString('ลบผู้ใช้', $html, $url);
            $this->assertStringNotContainsString('name="_method" value="DELETE"', $html, $url);
        }
    }

    public function test_editing_a_user_still_works(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'member']);

        $this->actingAs($admin)->get(route('admin.users.edit', $other))->assertOk();
        $this->assertTrue(Route::has('admin.users.update'));
    }

    public function test_nobody_can_delete_their_own_account_from_the_profile_route(): void
    {
        foreach (['admin', 'it_support', 'member'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertStatus(405);

            $this->assertNotNull(User::find($user->id), "$role account must still exist");
        }

        $this->assertFalse(Route::has('profile.destroy'));
    }
}
