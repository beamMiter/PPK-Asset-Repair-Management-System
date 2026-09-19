<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Accounts are suspended, never deleted (see UserDeletionRemovedTest): a suspended user keeps every record but cannot
 * sign in, keep a session / API token, or be given new work; only an admin can suspend or reactivate.
 */
class AccountSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private const CID = '1234567890123';

    private function suspended(array $extra = []): User
    {
        return User::factory()->create($extra + ['role' => 'it_support', 'citizen_id' => self::CID, 'suspended_at' => now()]);
    }

    // ---- model ------------------------------------------------------------------------------------------------

    public function test_a_new_account_is_active_and_the_active_scope_hides_suspended_ones(): void
    {
        $active = User::factory()->create();
        $gone = $this->suspended(['citizen_id' => '9999999999999']);

        $this->assertFalse($active->fresh()->isSuspended());
        $this->assertTrue($gone->fresh()->isSuspended());
        $this->assertSame([$active->id], User::active()->whereIn('id', [$active->id, $gone->id])->pluck('id')->all());
    }

    // ---- sign-in ----------------------------------------------------------------------------------------------

    public function test_a_suspended_user_cannot_sign_in_and_is_told_why_only_with_the_right_password(): void
    {
        $this->suspended();

        $this->post('/login', ['citizen_id' => self::CID, 'password' => 'password'])
            ->assertSessionHasErrors(['citizen_id' => EnsureAccountIsActive::MESSAGE]);
        $this->assertGuest();

        // a wrong password gets the ordinary message — the suspension is not revealed to someone guessing
        $this->post('/login', ['citizen_id' => self::CID, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['citizen_id' => 'เลขบัตรประชาชนหรือรหัสผ่านไม่ถูกต้อง']);
        $this->assertGuest();
    }

    public function test_an_active_user_still_signs_in(): void
    {
        User::factory()->create(['citizen_id' => self::CID, 'role' => 'member']);

        $this->post('/login', ['citizen_id' => self::CID, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_reactivating_lets_the_user_sign_in_again(): void
    {
        $user = $this->suspended();
        $this->post('/login', ['citizen_id' => self::CID, 'password' => 'password']);
        $this->assertGuest();

        $user->forceFill(['suspended_at' => null])->save();

        $this->post('/login', ['citizen_id' => self::CID, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    // ---- sessions and tokens ----------------------------------------------------------------------------------

    public function test_an_open_web_session_is_ended_on_the_next_request(): void
    {
        $user = $this->suspended();

        $this->actingAs($user)->get(route('repair.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['citizen_id' => EnsureAccountIsActive::MESSAGE]);
        $this->assertGuest();
    }

    public function test_an_ajax_request_from_a_suspended_session_gets_403_json(): void
    {
        $this->actingAs($this->suspended())->getJson(route('repair.dashboard'))
            ->assertForbidden()
            ->assertJson(['code' => 'account_suspended']);
    }

    public function test_api_tokens_and_api_login_are_refused(): void
    {
        $user = $this->suspended();

        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/auth/me')->assertForbidden()->assertJson(['code' => 'account_suspended']);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['citizen_id' => self::CID, 'password' => 'password'])
            ->assertForbidden()
            ->assertJson(['code' => 'account_suspended']);
    }

    public function test_an_active_user_keeps_api_access(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'member']), ['*']);

        $this->getJson('/api/auth/me')->assertOk();
    }

    // ---- admin actions ----------------------------------------------------------------------------------------

    public function test_admin_suspends_and_reactivates_and_history_is_untouched(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);
        $req = MaintenanceRequest::factory()->create(['reporter_id' => $member->id]);
        $member->createToken('phone');

        $this->actingAs($admin)->patch(route('admin.users.suspend', $member))
            ->assertRedirect()->assertSessionHas('toast.type', 'success');

        $member->refresh();
        $this->assertTrue($member->isSuspended());
        $this->assertSame(0, $member->tokens()->count(), 'API tokens are revoked at once');
        $this->assertSame($member->id, $req->fresh()->reporter_id, 'the request keeps its reporter');
        $this->assertNotNull(User::find($member->id));

        $this->actingAs($admin)->patch(route('admin.users.reactivate', $member))
            ->assertRedirect()->assertSessionHas('toast.type', 'success');
        $this->assertFalse($member->fresh()->isSuspended());
    }

    public function test_you_cannot_suspend_yourself_and_repeat_actions_are_harmless(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = $this->suspended(['citizen_id' => '2222222222222']);

        $this->actingAs($admin)->patch(route('admin.users.suspend', $admin))->assertSessionHas('toast.type', 'error');
        $this->assertFalse($admin->fresh()->isSuspended());

        $this->actingAs($admin)->patch(route('admin.users.suspend', $other))->assertSessionHas('toast.type', 'warning');
        $this->actingAs($admin)->patch(route('admin.users.reactivate', $admin))->assertSessionHas('toast.type', 'warning');
    }

    /** @return array<string, array{0: string}> */
    public static function nonAdminRoles(): array
    {
        return ['supervisor' => ['supervisor'], 'it_support' => ['it_support'], 'technician' => ['technician'], 'member' => ['member']];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_only_admin_can_suspend_or_reactivate(string $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $target = User::factory()->create(['role' => 'member']);
        $gone = $this->suspended(['citizen_id' => '3333333333333']);

        $this->actingAs($actor)->patch(route('admin.users.suspend', $target))->assertRedirect()->assertSessionHas('toast.type', 'error');
        $this->actingAs($actor)->patch(route('admin.users.reactivate', $gone))->assertRedirect()->assertSessionHas('toast.type', 'error');

        $this->assertFalse($target->fresh()->isSuspended());
        $this->assertTrue($gone->fresh()->isSuspended());
    }

    // ---- no new work ------------------------------------------------------------------------------------------

    public function test_a_suspended_user_cannot_be_assigned_and_is_not_offered_by_the_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $gone = $this->suspended();
        $active = User::factory()->create(['role' => 'it_support', 'citizen_id' => '4444444444444']);
        $req = MaintenanceRequest::factory()->create(['status' => MaintenanceRequest::STATUS_ACCEPTED]);

        $this->actingAs($admin)->post(route('maintenance.requests.assignments.store', $req), ['user_ids' => [$gone->id]])
            ->assertSessionHasErrors('user_ids.0');
        $this->assertSame(0, MaintenanceAssignment::where('user_id', $gone->id)->count());

        Sanctum::actingAs($admin, ['*']);
        $ids = collect($this->getJson('/api/meta/users?role=it_support')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($gone->id, $ids);
    }

    // ---- what the admin sees ----------------------------------------------------------------------------------

    public function test_the_user_list_and_edit_page_show_status_and_the_right_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $active = User::factory()->create(['role' => 'member', 'citizen_id' => '5555555555555']);
        $gone = $this->suspended(['citizen_id' => '6666666666666']);

        $list = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();
        $this->assertStringContainsString(route('admin.users.suspend', $active), $list);
        $this->assertStringContainsString(route('admin.users.reactivate', $gone), $list);
        $this->assertStringNotContainsString(route('admin.users.reactivate', $active), $list);
        $this->assertStringNotContainsString(route('admin.users.suspend', $gone), $list);
        $this->assertStringNotContainsString(route('admin.users.suspend', $admin), $list, 'no action on your own row');
        $this->assertMatchesRegularExpression('/ring-amber-200">ระงับ<\/span>/u', $list, 'suspended badge');

        $edit = $this->actingAs($admin)->get(route('admin.users.edit', $gone))->assertOk()->getContent();
        $this->assertStringContainsString('ถูกระงับ (ตั้งแต่', $edit);
        $this->assertStringContainsString(route('admin.users.reactivate', $gone), $edit);
        $this->assertStringContainsString('ไม่สามารถระงับบัญชีของตัวเองได้', $this->actingAs($admin)->get(route('admin.users.edit', $admin))->getContent());
    }
}
