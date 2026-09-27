<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * A failed login used to be completely silent:
 *  - the controller flashed a `toast`, but layouts/auth only had <x-toast />, which *consumes*
 *    session('toast') without rendering it (the renderer lives in layouts/app), and the
 *    login page's own fallback skips itself when a toast exists;
 *  - the @error blocks under the fields were empty;
 *  - a lock-out looked exactly like "wrong password";
 *  - the TokenMismatchException render callback never matched (Laravel converts it to a 419
 *    HttpException first), so users got the bare "419 | Page Expired" page.
 */
class LoginFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private const WRONG = 'เลขบัตรประชาชนหรือรหัสผ่านไม่ถูกต้อง';

    public function test_wrong_credentials_are_shown_as_a_toast_and_inline(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['citizen_id' => $user->citizen_id, 'password' => 'not-the-password'])
            ->assertRedirect('/login');

        $page = $this->get('/login');

        // toast carrier is rendered (this is what the auth layout was missing)…
        $page->assertSee('id="session-toast-data"', false);
        // …with the real message, and the same message sits under the field for no-JS/blocked-JS.
        $page->assertSee(self::WRONG);
        $page->assertSee('role="alert"', false);
    }

    public function test_lockout_says_to_wait_instead_of_wrong_password(): void
    {
        $cid = '9999999999999';

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', ['citizen_id' => $cid, 'password' => 'nope']);
        }

        $this->from('/login')->post('/login', ['citizen_id' => $cid, 'password' => 'nope']);
        $page = $this->get('/login');

        $page->assertSee('กรุณารอ');
        $page->assertSee('วินาที');
        $page->assertDontSee(self::WRONG);
    }

    public function test_format_errors_are_in_thai_and_inline(): void
    {
        $this->from('/login')
            ->post('/login', ['citizen_id' => '1-2345', 'password' => ''])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['citizen_id', 'password']);

        $this->get('/login')
            ->assertSee('เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก')
            ->assertSee('กรุณากรอกรหัสผ่าน');
    }

    public function test_expired_page_redirects_back_with_a_message_instead_of_the_bare_419(): void
    {
        Route::middleware('web')->post('/__test/expired', fn () => throw new TokenMismatchException('CSRF token mismatch.'));

        $this->from('/login')
            ->post('/__test/expired')
            ->assertRedirect('/login')
            ->assertSessionHas('toast.type', 'warning');
    }
}
