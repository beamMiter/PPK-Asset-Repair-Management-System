<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuts a suspended account off on its very next request — an existing session, a "remember me" cookie or an API
 * token must not outlive the suspension. Guests and active users pass straight through.
 */
class EnsureAccountIsActive
{
    public const MESSAGE = 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuspended()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => self::MESSAGE, 'code' => 'account_suspended'], Response::HTTP_FORBIDDEN);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['citizen_id' => self::MESSAGE]);
    }
}
