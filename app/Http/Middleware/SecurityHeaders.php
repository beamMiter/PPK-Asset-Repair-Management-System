<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Response headers that keep a page from being used against the people who open it — above all the sign-in page: with none of
 * these, another site could put this one in an invisible frame and have the staff type their password "into" it.
 *
 *  - X-Frame-Options / CSP `frame-ancestors`  only this site may frame its pages (both: an older browser knows only the first)
 *  - CSP `base-uri`                           an injected <base> cannot point every relative link and form at another site
 *  - X-Content-Type-Options                   a browser does not guess a type other than the one the response states
 *  - Referrer-Policy                          a link to another site carries this site's address without the path (a reset link
 *                                             has its token in the path)
 *  - Strict-Transport-Security                production over https only: the browser then refuses plain http for this host
 *
 * A header the response already carries is left as it is (the attachment download sets its own nosniff). Not a full
 * Content-Security-Policy on purpose: the pages take scripts from a CDN and carry inline Alpine, and a policy that forbids them
 * would break every screen — that one needs its own piece of work.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "frame-ancestors 'self'; base-uri 'self'",
        ];

        if ($request->isSecure() && app()->environment('production')) {
            $headers['Strict-Transport-Security'] = 'max-age=15552000';   // 180 days, this host only
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
