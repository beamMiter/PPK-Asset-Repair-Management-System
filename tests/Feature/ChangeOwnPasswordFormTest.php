<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRating;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Nobody could change their own password: PUT /password and its controller were there (and tested), the form was not.
 */
class ChangeOwnPasswordFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $sup;

    private User $tech;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->sup = User::factory()->create(['role' => 'supervisor']);
        $this->tech = User::factory()->create(['role' => 'it_support', 'name' => 'ช่างเอ']);
        $this->member = User::factory()->create(['role' => 'member']);
    }

    public function test_the_profile_page_has_a_change_password_form(): void
    {
        $html = $this->actingAs($this->member)->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('action="' . route('password.update') . '"', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" name="_method" value="PUT"/', $html);
        foreach (['current_password', 'password', 'password_confirmation'] as $field) {
            $this->assertStringContainsString('name="' . $field . '"', $html, $field);
        }
        $this->assertStringContainsString('อย่างน้อย 8 ตัวอักษร ต้องมีทั้งตัวอักษรและตัวเลข', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
        $this->assertStringContainsString('autocomplete="new-password"', $html);
    }

    private function changePassword(array $body)
    {
        return $this->actingAs($this->member)->from(route('profile.edit'))->put(route('password.update'), $body);
    }

    public function test_a_wrong_current_password_says_so_on_the_profile_page_and_changes_nothing(): void
    {
        $before = $this->member->fresh()->password;

        $this->changePassword(['current_password' => 'not-it', 'password' => 'Newpass123', 'password_confirmation' => 'Newpass123'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');
        $message = session('errors')->getBag('updatePassword')->first('current_password');

        $this->assertSame($before, $this->member->fresh()->password);
        $this->assertNotSame('', $message);
        $this->actingAs($this->member)->get(route('profile.edit'))->assertSee($message);   // the page prints it under the field
    }

    public function test_a_weak_new_password_is_refused_in_thai(): void
    {
        $this->changePassword(['current_password' => 'password', 'password' => 'onlyletters', 'password_confirmation' => 'onlyletters'])
            ->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertStringContainsString('รหัสผ่าน', session('errors')->getBag('updatePassword')->first('password'));
    }

    public function test_the_new_password_works_and_the_old_one_does_not_and_other_logins_end(): void
    {
        $token = $this->member->createToken('phone');

        $this->changePassword(['current_password' => 'password', 'password' => 'Newpass123', 'password_confirmation' => 'Newpass123'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');

        $this->assertTrue(Hash::check('Newpass123', $this->member->fresh()->password));
        $this->assertSame(0, $this->member->tokens()->count(), 'the phone\'s API token ended');

        $this->actingAs($this->member)->get(route('profile.edit'))->assertSee('เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');

        auth()->logout();
        $this->flushSession();
        $this->post('/login', ['citizen_id' => $this->member->citizen_id, 'password' => 'password']);
        $this->assertGuest();
        $this->post('/login', ['citizen_id' => $this->member->citizen_id, 'password' => 'Newpass123']);
        $this->assertAuthenticatedAs($this->member);
    }
}
