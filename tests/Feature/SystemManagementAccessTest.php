<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sidebar "การจัดการระบบ" — maintenance types, notification sounds and user admin — is for the admin role only.
 * Before, the types and the (shared) sound library were open to supervisors and every worker role, and the
 * notification controller only turned `member` away.
 */
class SystemManagementAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function nonAdminRoles(): array
    {
        return [
            'supervisor' => ['supervisor'],
            'it_support' => ['it_support'],
            'network' => ['network'],
            'programmer' => ['programmer'],
            'technician' => ['technician'],
            'member' => ['member'],
        ];
    }

    /** @return array<string, array{0: string, 1: string}> route name + method — route() cannot run before the app boots */
    public static function managementRoutes(): array
    {
        return [
            'types index' => ['settings.maintenance-types.index', 'get'],
            'types create form' => ['settings.maintenance-types.create', 'get'],
            'types store' => ['settings.maintenance-types.store', 'post'],
            'notifications index' => ['settings.notifications.index', 'get'],
            'notifications update' => ['settings.notifications.update_sound', 'patch'],
            'notifications upload' => ['settings.notifications.upload_sound', 'post'],
            'notifications destroy' => ['settings.notifications.destroy_sound', 'delete'],
            'users index' => ['admin.users.index', 'get'],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_only_admin_holds_the_system_gate(string $role): void
    {
        $this->assertFalse(User::factory()->create(['role' => $role])->can('manage-system'));
        $this->assertTrue(User::factory()->create(['role' => 'admin'])->can('manage-system'));
    }

    #[DataProvider('nonAdminRoles')]
    public function test_every_management_page_and_action_turns_non_admins_away(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        foreach (self::managementRoutes() as [$name, $method]) {
            // AuthorizationException → redirect back / to the dashboard with an error toast (a validation
            // redirect would not carry the toast, so this cannot pass for the wrong reason)
            $this->actingAs($user)
                ->{$method}(route($name))
                ->assertRedirect()
                ->assertSessionHas('toast.type', 'error');
        }
    }

    public function test_a_denied_action_has_no_side_effects(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $before = MaintenanceRequestType::count();

        $this->actingAs($supervisor)->post(route('settings.maintenance-types.store'), [
            'name' => 'Sneaky type', 'code' => 'SNK', 'default_resolution_minutes' => 30,
        ])->assertRedirect();

        $this->assertSame($before, MaintenanceRequestType::count());
        $this->assertNull(MaintenanceRequestType::where('name', 'Sneaky type')->first());
    }

    public function test_admin_reaches_every_management_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['settings.maintenance-types.index', 'settings.maintenance-types.create', 'settings.notifications.index', 'admin.users.index'] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }
    }

    public function test_the_sidebar_section_is_only_rendered_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin)->get(route('settings.maintenance-types.index'))->getContent();
        $this->assertStringContainsString('การจัดการระบบ', $html);
        $this->assertStringContainsString(route('settings.notifications.index'), $html);
        $this->assertStringContainsString(route('admin.users.index'), $html);

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $html = $this->actingAs($supervisor)->get(route('maintenance.sla.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('การจัดการระบบ', $html);
        $this->assertStringNotContainsString(route('settings.maintenance-types.index'), $html, 'no link the role cannot open');
        $this->assertStringNotContainsString(route('settings.notifications.index'), $html);
    }

    /** the SLA dashboard and technician board keep their own (wider) gate — the split must not lock them */
    public function test_supervisors_keep_the_sla_dashboard_and_the_technician_board(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->assertTrue($supervisor->can('maintenance-type-manage'));
        $this->actingAs($supervisor)->get(route('maintenance.sla.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('maintenance.requests.rating.technicians'))->assertOk();
    }

    /** Accounts are made by people signing themselves up (and an admin then sets the role); there is no "create a user" page or endpoint. */
    public function test_an_admin_does_not_create_users_through_the_admin_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.users.create'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.users.store'));
        $this->assertContains($this->actingAs($admin)->get('/admin/users/create')->getStatusCode(), [404, 405], 'nothing answers there');
        $this->actingAs($admin)->post('/admin/users', ['name' => 'x', 'citizen_id' => '1234500000000', 'password' => 'Abcdefg123', 'password_confirmation' => 'Abcdefg123', 'role' => 'member'])->assertStatus(405);
        $this->assertSame(0, User::where('citizen_id', '1234500000000')->count());

        $list = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('สร้างผู้ใช้ใหม่', $list, 'no button that leads nowhere');
    }
}
