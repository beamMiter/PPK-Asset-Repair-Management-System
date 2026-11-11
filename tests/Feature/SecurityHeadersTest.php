<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Without these headers another site could put the sign-in page in an invisible frame and have staff type their password into
 * it. The enforced Content-Security-Policy is only what cannot break a page (`frame-ancestors`, `base-uri`, `object-src`, `form-action`);
 * the strict policy the pages would need is sent report-only outside production, so it blocks nothing.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function assertHardened($response, string $where): void
    {
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertSame("frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'", $response->headers->get('Content-Security-Policy'), $where);
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

    // ---- the CSP: enforced only where it cannot break a page ----------------------------------------------------------

    public function test_the_enforced_policy_holds_no_script_or_style_rule(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        foreach (['script-src', 'style-src', 'default-src', 'connect-src', 'img-src', 'font-src'] as $directive) {
            $this->assertStringNotContainsString($directive, $csp, "$directive is not enforced: it would break the pages");
        }
        foreach (['frame-ancestors', 'base-uri', 'object-src', 'form-action'] as $directive) {
            $this->assertStringContainsString($directive, $csp);
        }
    }

    public function test_the_strict_preview_is_report_only_outside_production_and_names_no_cdn(): void
    {
        $report = $this->get('/login')->assertOk()->headers->get('Content-Security-Policy-Report-Only');

        $this->assertNotNull($report);
        $this->assertStringContainsString("default-src 'self'", $report);
        $this->assertStringNotContainsString("'unsafe-inline'", $report, 'the point of the preview is to list what is inline');
        // the pages take nothing from another site any more (public/vendor), so the policy they will be held to names none
        foreach (['cdn.jsdelivr.net', 'unpkg.com', 'cdnjs.cloudflare.com', 'fonts.googleapis.com', 'fonts.gstatic.com', 'fonts.bunny.net', 'mixkit.co'] as $host) {
            $this->assertStringNotContainsString($host, $report, $host);
        }
        $this->assertStringContainsString("media-src 'self';", $report);
        // the one other site the app talks to: Pusher, for the chat and the new-job bell
        $this->assertStringContainsString('wss://*.pusher.com', $report);
    }

    public function test_no_view_names_a_host_on_another_site(): void
    {
        // the policy names no CDN, so a view that took a file from one again would break the day it is enforced
        $found = [];
        foreach (\Illuminate\Support\Facades\File::allFiles(resource_path('views')) as $file) {
            preg_match_all('#(?:src|href)=["\']https?://([a-z0-9.-]+)#i', $file->getContents(), $m);
            foreach ($m[1] as $host) {
                $found[] = $file->getRelativePathname().': '.$host;
            }
        }

        $this->assertSame([], array_values(array_unique($found)));
    }

    public function test_production_sends_the_enforced_policy_only(): void
    {
        $this->app['env'] = 'production';

        $response = $this->get('/login');

        $this->assertSame(SecurityHeaders::ENFORCED, $response->headers->get('Content-Security-Policy'));
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_the_local_preview_also_allows_the_vite_dev_server(): void
    {
        $this->app['env'] = 'local';

        $report = $this->get('/login')->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringContainsString('http://localhost:*', $report);
        $this->assertStringContainsString('ws://localhost:*', $report);
    }

    public function test_a_report_only_header_the_response_already_has_is_left_alone(): void
    {
        Route::middleware('web')->get('/__test/own-report-only', fn () => response('ok')->header('Content-Security-Policy-Report-Only', "default-src 'none'"));

        $this->get('/__test/own-report-only')->assertHeader('Content-Security-Policy-Report-Only', "default-src 'none'");
    }
}
