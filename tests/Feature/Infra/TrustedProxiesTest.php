<?php

namespace Tests\Feature\Infra;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Who is calling, when there is a proxy in front of the app. Without a list of trusted proxies every user shares the proxy's address (the
 * sign-in limit per address would lock everybody together, the moderation record names the proxy), and the app cannot see that a page came
 * over https. With one, `X-Forwarded-*` is believed from those addresses only - anybody else who sends it is ignored.
 */
class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->get('/__test/caller', fn (Request $r) => response()->json(['ip' => $r->ip(), 'secure' => $r->isSecure()]));
    }

    private function fromAddress(string $from, array $headers = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $from])->getJson('/__test/caller', $headers);
    }

    public function test_by_default_nobody_is_a_proxy_and_a_forwarded_address_is_ignored(): void
    {
        config(['trustedproxy.proxies' => null]);

        $res = $this->fromAddress('10.0.0.2', ['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https']);

        $res->assertJsonPath('ip', '10.0.0.2')->assertJsonPath('secure', false);
    }

    public function test_a_listed_proxy_is_believed_about_the_caller_and_about_https(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8']);

        $res = $this->fromAddress('10.0.0.2', ['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https']);

        $res->assertJsonPath('ip', '203.0.113.9')->assertJsonPath('secure', true);
    }

    public function test_the_same_header_from_anyone_else_is_ignored(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8']);

        $res = $this->fromAddress('198.51.100.7', ['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https']);

        $res->assertJsonPath('ip', '198.51.100.7')->assertJsonPath('secure', false);
    }

    public function test_a_list_of_addresses_and_the_wildcard_both_work(): void
    {
        config(['trustedproxy.proxies' => '192.0.2.1, 10.1.1.1']);
        $this->fromAddress('10.1.1.1', ['X-Forwarded-For' => '203.0.113.9'])->assertJsonPath('ip', '203.0.113.9');
        $this->fromAddress('10.1.1.2', ['X-Forwarded-For' => '203.0.113.9'])->assertJsonPath('ip', '10.1.1.2');

        config(['trustedproxy.proxies' => '*']);
        $this->fromAddress('198.51.100.7', ['X-Forwarded-For' => '203.0.113.9'])->assertJsonPath('ip', '203.0.113.9');
    }

    public function test_behind_a_trusted_https_proxy_production_sends_hsts(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8']);
        $this->app['env'] = 'production';

        $res = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'localhost']);

        $res->assertHeader('Strict-Transport-Security', 'max-age=15552000');
    }

    public function test_the_config_file_takes_its_list_from_the_trusted_proxies_setting_and_an_empty_one_means_nobody(): void
    {
        $read = fn () => (require base_path('config/trustedproxy.php'))['proxies'];
        $before = $_ENV['TRUSTED_PROXIES'] ?? null;

        try {
            $_ENV['TRUSTED_PROXIES'] = '10.0.0.0/8,172.16.0.5';
            $this->assertSame('10.0.0.0/8,172.16.0.5', $read());

            $_ENV['TRUSTED_PROXIES'] = '';
            $this->assertNull($read(), 'an empty value is "nobody", not a list holding an empty string');
        } finally {
            $before === null ? \Illuminate\Support\Env::getRepository()->clear('TRUSTED_PROXIES') : $_ENV['TRUSTED_PROXIES'] = $before;
            unset($_ENV['TRUSTED_PROXIES']);
        }
    }
}
