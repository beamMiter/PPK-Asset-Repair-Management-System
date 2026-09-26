<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProductionHosts;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

/**
 * A request that names another host ("Host: evil.test") used to get the victim a genuine password-reset e-mail whose link led
 * to evil.test: `route()` builds its address from the Host header. The link is now built from APP_URL, and in production a Host
 * header this app is not known by is refused outright.
 */
class HostHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);   // static state: the next test must not inherit this one's list

        parent::tearDown();
    }

    public function test_the_reset_link_in_the_email_points_at_the_configured_address_whatever_host_asked(): void
    {
        config(['app.url' => 'https://ppk.example.go.th']);
        Notification::fake();
        $victim = User::factory()->create(['email' => 'victim@example.test']);

        $this->call('POST', 'http://evil.test/forgot-password', ['email' => 'victim@example.test']);

        Notification::assertSentTo($victim, ResetPassword::class, function (ResetPassword $n) use ($victim) {
            $url = $n->toMail($victim)->actionUrl;

            $this->assertStringStartsWith('https://ppk.example.go.th/reset-password/' . $n->token, $url);
            $this->assertStringNotContainsString('evil.test', $url);
            $this->assertStringContainsString('email=victim%40example.test', $url);

            return true;
        });
    }

    public function test_an_app_url_with_a_path_or_trailing_slash_still_gives_one_clean_link(): void
    {
        config(['app.url' => 'https://ppk.example.go.th/']);
        $user = User::factory()->create();

        $url = (new ResetPassword('tok'))->toMail($user)->actionUrl;

        $this->assertStringStartsWith('https://ppk.example.go.th/reset-password/tok?', $url);
    }

    public function test_the_trusted_hosts_are_the_app_url_host_and_the_listed_ones(): void
    {
        config(['app.url' => 'https://PPK.example.go.th', 'app.trusted_hosts' => ['10.0.0.5', ' Portal.example.go.th ']]);

        $this->assertSame(
            ['^ppk\.example\.go\.th$', '^10\.0\.0\.5$', '^portal\.example\.go\.th$'],
            (new TrustProductionHosts($this->app))->hosts(),
        );
    }

    public function test_an_app_url_that_is_still_the_machine_itself_restricts_nothing(): void
    {
        foreach (['http://localhost', 'http://localhost:8000', 'http://127.0.0.1:8000', '', 'not a url'] as $url) {
            config(['app.url' => $url]);
            $this->assertSame([], (new TrustProductionHosts($this->app))->hosts(), "APP_URL “{$url}”");
        }
    }

    public function test_a_foreign_host_is_refused_and_the_real_one_is_not(): void
    {
        config(['app.url' => 'https://ppk.example.go.th', 'app.trusted_hosts' => ['10.0.0.5']]);
        $guard = new class($this->app) extends TrustProductionHosts {
            protected function shouldSpecifyTrustedHosts(): bool
            {
                return true;   // the framework leaves the check off under test
            }
        };
        $next = fn () => response('ok');

        foreach (['https://ppk.example.go.th/login', 'http://10.0.0.5/login'] as $url) {
            $request = Request::create($url);
            $guard->handle($request, $next);
            $this->assertNotSame('', $request->getHost(), $url);
        }

        $request = Request::create('http://evil.test/forgot-password');
        $guard->handle($request, $next);
        $this->expectException(SuspiciousOperationException::class);
        $request->getHost();
    }

    public function test_the_guard_is_in_the_global_middleware_stack_first(): void
    {
        $stack = $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->getGlobalMiddleware();

        $this->assertSame(TrustProductionHosts::class, $stack[0]);
    }
}
