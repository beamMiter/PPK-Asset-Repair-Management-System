<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts;

/**
 * In production only the host names this app is known by are accepted; a request that names another host in its Host header
 * is refused (400) before anything builds a link from it.
 *
 * Known by: the host of APP_URL, plus the list in APP_TRUSTED_HOSTS. Not enforced (as the framework's own middleware) in the
 * `local` environment or under test. And not enforced while APP_URL still names the machine itself (localhost / 127.0.0.1):
 * that is a setting nobody has filled in yet, and refusing every real host because of it would lock everyone out.
 */
class TrustProductionHosts extends TrustHosts
{
    private const UNSET_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]'];

    public function hosts(): array
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! $appHost || in_array(strtolower($appHost), self::UNSET_HOSTS, true)) {
            return [];   // an empty list restricts nothing
        }

        return collect([$appHost, ...(array) config('app.trusted_hosts', [])])
            ->map(fn ($host) => '^' . preg_quote(strtolower(trim((string) $host)), '#') . '$')
            ->unique()
            ->values()
            ->all();
    }
}
