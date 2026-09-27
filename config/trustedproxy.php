<?php

/**
 * The proxies this app may believe about who is really calling (Laravel's own TrustProxies middleware reads this file).
 *
 * Behind a reverse proxy or load balancer - the hospital's, or one in front of nginx - every request reaches PHP from the PROXY's address, so
 * `$request->ip()` is the same for everybody: the sign-in limit of "30 wrong tries from one address" would lock the whole hospital at once, the
 * moderation record would name the proxy, and an https page would look like plain http (no HSTS, http links). The proxy says who called in
 * `X-Forwarded-For` / `-Proto`; those headers are believed ONLY from an address listed here, because anybody can send them.
 *
 * TRUSTED_PROXIES in .env:  (empty)  nobody is a proxy - what a machine with no proxy in front of it needs (the default)
 *                           10.0.0.0/8,172.16.0.5   the proxy's address(es), comma separated, CIDR allowed
 *                           *        any caller - ONLY if the app can be reached through the proxy alone (never from the open network)
 */
return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
