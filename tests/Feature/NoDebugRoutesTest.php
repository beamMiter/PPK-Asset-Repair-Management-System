<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * `GET /debug/login` used to sign anybody in as user 410 with no password and no middleware. Nothing under a
 * "debug" path (or any other credential-free way to get a session) may be registered.
 */
class NoDebugRoutesTest extends TestCase
{
    public function test_no_route_is_registered_under_a_debug_path(): void
    {
        $debug = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($uri) => str_contains($uri, 'debug'))
            ->values()
            ->all();

        $this->assertSame([], $debug);
    }

    public function test_a_guest_cannot_get_a_session_from_the_old_debug_urls(): void
    {
        $this->get('/debug/login')->assertNotFound();
        $this->assertGuest();
        $this->get('/debug/whoami')->assertNotFound();
    }

    public function test_every_route_in_the_router_points_at_a_method_that_exists(): void
    {
        $broken = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => is_string($r->getActionName()) && str_contains($r->getActionName(), '@'))
            ->filter(function ($r) {
                [$class, $method] = explode('@', $r->getActionName());

                return ! class_exists($class) || ! method_exists($class, $method);
            })
            ->map(fn ($r) => $r->methods()[0].' '.$r->uri().' -> '.$r->getActionName())
            ->values()
            ->all();

        $this->assertSame([], $broken);
    }
}
