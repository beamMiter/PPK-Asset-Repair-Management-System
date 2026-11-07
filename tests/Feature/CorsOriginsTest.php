<?php

namespace Tests\Feature;

use App\Support\CorsOrigins;
use Tests\TestCase;

/**
 * `config/cors.php` named two developer addresses (localhost:8000, 127.0.0.1:8000) as the only sites allowed to call the
 * app with the user's cookies, in production too, and not the app's real address. The list now comes from APP_URL, plus
 * CORS_ALLOWED_ORIGINS for a separate front end. The app's own pages and the mobile app never needed it: same-site
 * requests and bearer tokens do not go through CORS.
 */
class CorsOriginsTest extends TestCase
{
    public function test_the_origin_is_the_scheme_host_and_port_of_app_url(): void
    {
        $this->assertSame(['https://repair.example.go.th'], CorsOrigins::for('https://repair.example.go.th/'));
        $this->assertSame(['http://10.0.0.5:8080'], CorsOrigins::for('http://10.0.0.5:8080/ppk/'));
        $this->assertSame(['https://repair.example.go.th'], CorsOrigins::for('HTTPS://Repair.Example.go.th'));
    }

    public function test_extra_origins_are_added_and_nothing_else_slips_in(): void
    {
        $this->assertSame(
            ['https://repair.example.go.th', 'https://app.example.go.th', 'http://localhost:5173'],
            CorsOrigins::for('https://repair.example.go.th', ' https://app.example.go.th/ ,http://localhost:5173,https://repair.example.go.th, *, ftp://x.example,,javascript:alert(1)')
        );
    }

    public function test_a_developer_address_is_not_allowed_unless_it_is_the_apps_own(): void
    {
        $this->assertNotContains('http://localhost:8000', CorsOrigins::for('https://repair.example.go.th'));
        $this->assertNotContains('http://127.0.0.1:8000', CorsOrigins::for('https://repair.example.go.th'));
        $this->assertSame([], CorsOrigins::for(null), 'no APP_URL, no cross-site caller');
    }

    public function test_the_config_is_built_from_app_url(): void
    {
        $this->assertSame(CorsOrigins::for(config('app.url'), (string) env('CORS_ALLOWED_ORIGINS')), config('cors.allowed_origins'));
    }

    public function test_the_apps_own_origin_may_call_with_cookies_and_another_site_may_not(): void
    {
        $own = CorsOrigins::for(config('app.url'))[0];
        $preflight = fn (string $origin) => $this->call('OPTIONS', '/api/auth/login', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $allowed = $preflight($own);
        $this->assertSame($own, $allowed->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $allowed->headers->get('Access-Control-Allow-Credentials'));

        $this->assertNotSame('https://evil.example', $preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'));
    }
}
