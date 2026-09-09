<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * AU2: the manage-users gate used to be admin + supervisor + every worker
 * role, so any IT worker could open /admin/users and read the whole staff
 * list. It is now admin-only.
 * AU1: the /admin/users/bulk route pointed at a non-existent controller
 * method and is removed.
 */
class AdminUsersAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_cannot_reach_the_user_admin(): void
    {
        foreach (['supervisor', 'it_support', 'member'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('admin.users.index'))
                ->assertRedirect();
        }
    }

    public function test_admin_can_reach_the_user_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_bulk_route_is_gone(): void
    {
        $this->assertFalse(Route::has('admin.users.bulk'));
    }
}
