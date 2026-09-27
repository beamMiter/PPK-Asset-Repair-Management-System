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
 *  - CSP `form-action`                        a form on this site can only post to this site: an injected `<form action="https://evil…">`
 *                                             cannot carry what someone types (a password) to another host
 *  - CSP `object-src 'none'`                  no plug-in content (<object> / <embed>); the app uses none
 *  - X-Content-Type-Options                   a browser does not guess a type other than the one the response states
 *  - Referrer-Policy                          a link to another site carries this site's address without the path (a reset link
 *                                             has its token in the path)
 *  - Strict-Transport-Security                production over https only: the browser then refuses plain http for this host
 *
 * A header the response already carries is left as it is (the attachment download sets its own nosniff).
 *
 * The ENFORCED policy is only the directives above, on purpose: they cannot break a page, because the app has no <base>, no
 * <object> / <embed>, no form that posts to another site (checked against every view), and only this site frames its pages. What a
 * full policy would add - `script-src` / `style-src` - is not enforced: the pages take scripts from three CDNs, carry inline Alpine
 * (`x-data`, ~60 inline handlers, 19 inline <script> blocks) and, in development, come from the Vite dev server; a policy that forbids
 * those would break every screen.
 *
 * Instead, while the app is not in production, the strict policy it would need is sent as `Content-Security-Policy-Report-Only`: it
 * blocks nothing, and the browser's console lists what a strict policy would refuse (the inline handlers and scripts) - the work list
 * for the day the pages move to nonces. Production carries no report-only header, so nobody there sees or pays for it.
 */
class SecurityHeaders
{
    /** Enforced: cannot break a page (see the class comment). */
    public const ENFORCED = "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'";

    /** The hosts the pages really take things from (every view and script checked): three script CDNs, the font services, the sound files, Pusher. */
    private const SCRIPT_HOSTS = 'https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com';
    private const STYLE_HOSTS = 'https://fonts.googleapis.com https://fonts.bunny.net https://cdn.jsdelivr.net https://cdnjs.cloudflare.com';
    private const FONT_HOSTS = 'https://fonts.gstatic.com https://fonts.bunny.net https://cdn.jsdelivr.net https://cdnjs.cloudflare.com';
    private const DEV_HOSTS = 'http://localhost:* http://127.0.0.1:* http://[::1]:*';   // the Vite dev server

    /** The strict policy the pages would have to meet before it can be enforced: no inline script, no inline style. Report-only. */
    public static function strictPreview(): string
    {
        $dev = app()->environment('local') ? ' ' . self::DEV_HOSTS : '';
        $devSockets = app()->environment('local') ? ' ws://localhost:* ws://127.0.0.1:*' : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' " . self::SCRIPT_HOSTS . $dev,
            "style-src 'self' " . self::STYLE_HOSTS . $dev,
            "font-src 'self' " . self::FONT_HOSTS . ' data:',
            "img-src 'self' data: blob:",
            "media-src 'self' https://assets.mixkit.co",
            "connect-src 'self' https://*.pusher.com wss://*.pusher.com" . $dev . $devSockets,
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => self::ENFORCED,
        ];

        if (! app()->environment('production')) {
            $headers['Content-Security-Policy-Report-Only'] = self::strictPreview();
        }

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
