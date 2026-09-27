<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `/api/health` used to report a "queue" check that only built the connection object — it never looked for a worker, so
 * it said "ok" whatever the state of the queue — for a queue the application does not use at all (no jobs, listeners,
 * mail or notifications are queued; the push events are `ShouldBroadcastNow`).
 */
class HealthEndpointTest extends TestCase
{
    public function test_health_reports_the_things_the_app_really_depends_on(): void
    {
        $res = $this->getJson('/api/health');

        $res->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('checks.db.status', 'ok');
        $this->assertArrayNotHasKey('queue', $res->json('checks'), 'nothing here is queued, so there is nothing to check');
        $this->assertSame(['app', 'env', 'time', 'version'], array_keys($res->json('meta')));
    }

    public function test_health_needs_no_login(): void
    {
        $this->getJson('/api/health')->assertOk();
    }

    public function test_nothing_in_the_application_uses_laravels_queue(): void
    {
        // If this starts failing, a queued job / listener / mail was added: put a worker back in `composer dev`
        // and a real queue check back in the health endpoint.
        $offenders = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            if (preg_match('/\bShouldQueue\b|\bQueueable\b|->onQueue\(|::dispatch\(|\bBus::|\bQueue::/', $code)) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders);
    }
}
