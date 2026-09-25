<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A password an admin chose for somebody is known to the admin and usually written on a slip of paper. The person must replace it before
 * they use the system: every page sends them to the profile page, every API call answers 403 `password_change_required`; the way out
 * (the profile page, the password form, signing out) stays open. Set when an admin creates an account or sets somebody else's password;
 * cleared when the person changes it, or resets it through the e-mail link.
 */
class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_CHOSEN = 'Welcome123';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** An account made through the admin's form, and the person's first sign-in. */
    private function createdByAdmin(array $extra = []): User
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), $extra + [
            'name' => 'พนักงานใหม่', 'citizen_id' => '1999999999999', 'email' => 'new@example.test', 'role' => 'member',
            'password' => self::ADMIN_CHOSEN, 'password_confirmation' => self::ADMIN_CHOSEN,
        ]);
        auth()->logout();
        $this->flushSession();

        return User::where('citizen_id', $extra['citizen_id'] ?? '1999999999999')->firstOrFail();
    }

    public function test_the_column_exists_and_nobody_is_forced_by_default(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'must_change_password'));
        $this->assertFalse((bool) User::factory()->create()->fresh()->must_change_password);
    }

    public function test_the_seeded_demo_accounts_are_not_forced(): void
    {
        $this->seed();

        $this->assertSame(0, User::where('must_change_password', true)->count());
    }

    public function test_an_account_the_admin_creates_must_change_its_password(): void
    {
        $person = $this->createdByAdmin();

        $this->assertTrue($person->must_change_password);
        $this->assertTrue(Hash::check(self::ADMIN_CHOSEN, $person->password));
    }

    public function test_every_page_sends_such_a_person_to_the_profile_page(): void
    {
        $person = $this->createdByAdmin();
        $this->post('/login', ['citizen_id' => $person->citizen_id, 'password' => self::ADMIN_CHOSEN]);
        $this->assertAuthenticatedAs($person);

        foreach (['/dashboard', '/repair/dashboard', '/maintenance/requests', '/assets', '/chat'] as $url) {
            $this->get($url)->assertRedirect(route('profile.edit'));
            $this->assertSame(EnsurePasswordIsChanged::MESSAGE, session('toast.message'), $url);
            $this->flushSession();
            $this->actingAs($person);
        }
    }

    public function test_the_way_out_stays_open_and_says_why(): void
    {
        $person = $this->createdByAdmin();

        $page = $this->actingAs($person)->get(route('profile.edit'))->assertOk();
        $page->assertSee('ผู้ดูแลระบบเป็นผู้ตั้งรหัสผ่านให้คุณ')->assertSee('name="current_password"', false);

        $this->actingAs($person)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_a_script_is_told_in_json(): void
    {
        $person = $this->createdByAdmin();

        $this->actingAs($person)->getJson('/repair/dashboard')->assertStatus(403)->assertJson(['code' => 'password_change_required']);
    }

    public function test_the_api_tells_the_client_at_sign_in_and_blocks_the_rest(): void
    {
        $person = $this->createdByAdmin();

        $login = $this->postJson('/api/auth/login', ['citizen_id' => $person->citizen_id, 'password' => self::ADMIN_CHOSEN])->assertCreated();
        $this->assertTrue($login->json('user.must_change_password'));

        Sanctum::actingAs($person->fresh());
        $this->getJson('/api/auth/me')->assertStatus(403)->assertJson(['code' => 'password_change_required']);
        $this->getJson('/api/assets')->assertStatus(403)->assertJson(['code' => 'password_change_required']);
        $this->postJson('/api/auth/logout')->assertOk();   // the way out
    }

    public function test_changing_it_lets_them_in_and_it_cannot_be_the_same_password(): void
    {
        $person = $this->createdByAdmin();

        $this->actingAs($person)->from(route('profile.edit'))->put(route('password.update'), [
            'current_password' => self::ADMIN_CHOSEN, 'password' => self::ADMIN_CHOSEN, 'password_confirmation' => self::ADMIN_CHOSEN,
        ])->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->assertTrue($person->fresh()->must_change_password, 'the same password is not a change');

        $this->actingAs($person)->put(route('password.update'), [
            'current_password' => self::ADMIN_CHOSEN, 'password' => 'MyOwn12345', 'password_confirmation' => 'MyOwn12345',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('success', session('toast.type'));
        $this->assertFalse($person->fresh()->must_change_password);
        $this->assertTrue(Hash::check('MyOwn12345', $person->fresh()->password));
        $this->actingAs($person->fresh())->get('/repair/dashboard')->assertOk();
    }

    public function test_choosing_a_password_through_the_e_mail_link_clears_it_too(): void
    {
        $person = $this->createdByAdmin();

        $this->post('/reset-password', [
            'token' => Password::createToken($person), 'email' => $person->email, 'password' => 'FromEmail123', 'password_confirmation' => 'FromEmail123',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($person->fresh()->must_change_password);
    }

    public function test_an_admin_setting_somebody_elses_password_forces_a_change_but_their_own_does_not(): void
    {
        $person = User::factory()->create(['role' => 'member', 'citizen_id' => '1888888888888']);
        $edit = fn (User $who, array $body) => $this->actingAs($this->admin)->put(route('admin.users.update', $who), $body + [
            'name' => $who->name, 'citizen_id' => $who->citizen_id, 'email' => $who->email, 'role' => $who->role,
        ]);

        $edit($person, ['password' => 'ChosenByAdmin1', 'password_confirmation' => 'ChosenByAdmin1']);
        $this->assertTrue($person->fresh()->must_change_password, 'somebody else\'s password, chosen by the admin');

        $person->forceFill(['must_change_password' => false])->save();
        $edit($person, []);   // an edit that leaves the password alone
        $this->assertFalse($person->fresh()->must_change_password);

        $edit($this->admin, ['password' => 'MyOwnAdmin123', 'password_confirmation' => 'MyOwnAdmin123']);
        $this->assertFalse($this->admin->fresh()->must_change_password, 'an admin changing their own is not asked to change it again');
    }

    public function test_signing_up_yourself_is_not_forced(): void
    {
        $this->post('/register', ['name' => 'สมัครเอง', 'citizen_id' => '1777777777777', 'password' => 'Mine123456', 'password_confirmation' => 'Mine123456']);

        $this->assertFalse(User::where('citizen_id', '1777777777777')->firstOrFail()->must_change_password);
    }
}
