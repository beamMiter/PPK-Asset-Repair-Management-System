<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Without these headers another site could put the sign-in page in an invisible frame and have staff type their password into
 * it. (No Content-Security-Policy beyond `frame-ancestors` / `base-uri`: the pages need a CDN and inline Alpine.)
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function assertHardened($response, string $where): void
    {
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertSame("frame-ancestors 'self'; base-uri 'self'", $response->headers->get('Content-Security-Policy'), $where);
    }

    public function test_the_sign_in_page_cannot_be_framed_by_another_site(): void
    {
        $this->assertHardened($this->get('/login'), 'login');
    }

    public function test_a_signed_in_page_a_missing_page_and_the_api_carry_them_too(): void
    {
        $this->assertHardened($this->actingAs(User::factory()->create())->get('/dashboard'), 'dashboard');
        $this->assertHardened($this->get('/no-such-page'), '404');
        $this->assertHardened($this->getJson('/api/health'), 'api');
        $this->assertHardened($this->postJson('/api/auth/login', ['citizen_id' => 'x']), 'api validation error');
    }

    public function test_a_header_the_response_already_has_is_left_alone(): void
    {
        Route::middleware('web')->get('/__test/framable', fn () => response('ok')->header('X-Frame-Options', 'DENY'));

        $this->get('/__test/framable')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_hsts_is_only_for_production_over_https(): void
    {
        $this->get('https://localhost/login')->assertHeaderMissing('Strict-Transport-Security');   // testing env

        $this->app['env'] = 'production';

        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=15552000');
    }
}
