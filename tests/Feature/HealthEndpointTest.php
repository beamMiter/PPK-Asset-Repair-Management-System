<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
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

    public function test_a_redis_pong_is_recognised_from_either_client(): void
    {
        // phpredis (the Docker image's client) answers PING with `true` since 5.0; predis with "PONG". The check compared the
        // reply to "pong" only, so with phpredis and a healthy Redis it reported "unexpected" and the endpoint answered 503.
        $this->assertTrue(HealthController::pongReceived(true), 'phpredis');
        $this->assertTrue(HealthController::pongReceived('PONG'), 'predis');
        $this->assertTrue(HealthController::pongReceived('+PONG'), 'a raw status reply');
        $this->assertFalse(HealthController::pongReceived(false));
        $this->assertFalse(HealthController::pongReceived('1'));
        $this->assertFalse(HealthController::pongReceived(null));
    }

    public function test_health_needs_no_login(): void
    {
        $this->getJson('/api/health')->assertOk();
    }

    public function test_a_failing_database_is_reported_without_its_own_message(): void
    {
        // The endpoint needs no sign-in, so whoever asks gets the answer. A connection error carries the host, the port, the database
        // name and the SQL ("… (Connection: mysql, Host: …, Port: 3306, Database: …, SQL: select 1)"): it went out as `error` to
        // anybody, at exactly the moment the system was in trouble. The answer says which check failed; the message goes to the log.
        $message = 'SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, Host: db-internal-host, Port: 3306, Database: ppk_secret_db, SQL: select 1)';
        DB::partialMock()->shouldReceive('select')->andThrow(new \RuntimeException($message));
        Log::spy();

        $res = $this->getJson('/api/health');

        $res->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.db.status', 'error')
            ->assertJsonPath('checks.db.error', 'check failed');
        foreach (['db-internal-host', 'ppk_secret_db', 'SQLSTATE', 'select 1', '3306'] as $secret) {
            $this->assertStringNotContainsString($secret, $res->getContent(), "the answer leaked `{$secret}`");
        }

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($text, $context = []) => str_contains($text, 'database') && ($context['error'] ?? null) === $message)
            ->once();
    }

    public function test_a_failing_redis_is_reported_without_its_own_message(): void
    {
        if (! class_exists('Redis')) {
            $this->markTestSkipped('phpredis is not installed here (CI and the Docker image have it); without it the endpoint skips the check');
        }

        $message = 'Connection refused [tcp://redis-internal-host:6379]';
        Redis::shouldReceive('connection')->andThrow(new \RuntimeException($message));
        Log::spy();

        $res = $this->getJson('/api/health');

        $res->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.redis.status', 'error')
            ->assertJsonPath('checks.redis.error', 'check failed');
        $this->assertStringNotContainsString('redis-internal-host', $res->getContent());

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($text, $context = []) => str_contains($text, 'redis') && ($context['error'] ?? null) === $message)
            ->once();
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
