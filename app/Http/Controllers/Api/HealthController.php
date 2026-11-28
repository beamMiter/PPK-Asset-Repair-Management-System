<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    /**
     * Whether a Redis client's answer to PING says the server is there. phpredis (the Docker image's client) answers `true`
     * since 5.0; predis answers "PONG". Comparing the reply to "pong" alone reported a healthy Redis as "unexpected", and the
     * endpoint answered 503, wherever phpredis is installed.
     */
    public static function pongReceived(mixed $reply): bool
    {
        if ($reply === true) {
            return true;
        }

        return (is_string($reply) || $reply instanceof \Stringable) && strtoupper(ltrim((string) $reply, '+')) === 'PONG';
    }

    /**
     * What a failed check shows. This endpoint needs no sign-in, so whoever asks gets the answer, and the failure's own message
     * (a connection error names the host, the port, the database and the SQL) went out to all of them at the very moment the system
     * was in trouble. The answer says which check failed and a fixed word; the message is written to the log, where the people
     * who run the server look.
     */
    private function failed(string $check, Throwable $e): array
    {
        Log::error("[health] the {$check} check failed", ['error' => $e->getMessage(), 'exception' => $e::class]);

        return [
            'status' => 'error',
            'error' => 'check failed',
        ];
    }

    public function index(Request $request)
    {
        $checks = [];
        $overallOk = true;

        $start = microtime(true);
        try {
            DB::select('select 1');
            $checks['db'] = [
                'status' => 'ok',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (Throwable $e) {
            $overallOk = false;
            $checks['db'] = $this->failed('database', $e);
        }

        try {
            if (class_exists('Redis')) {
                $pong = Redis::connection()->ping();
                $ok = self::pongReceived($pong);
                $checks['redis'] = [
                    'status' => $ok ? 'ok' : 'unexpected',
                    'reply' => $pong === true ? 'PONG' : (is_string($pong) ? $pong : var_export($pong, true)),
                ];
                if (! $ok) {
                    $overallOk = false;
                }
            } else {
                $checks['redis'] = [
                    'status' => 'skipped',
                    'reason' => 'phpredis extension not installed',
                ];
            }
        } catch (Throwable $e) {
            $overallOk = false;
            $checks['redis'] = $this->failed('redis', $e);
        }

        $status = $overallOk ? 'ok' : 'degraded';
        $httpStatus = $overallOk ? 200 : 503;

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'meta' => [
                'app' => config('app.name'),
                'env' => config('app.env'),
                'time' => now()->toIso8601String(),
                'version' => config('app.version') ?? null,
            ],
        ], $httpStatus);
    }
}
