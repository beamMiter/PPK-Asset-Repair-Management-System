<?php

namespace App\Http\Middleware;

use App\Support\Toast;
use Closure;
use Illuminate\Http\Request;

/**
 * A person whose password an admin chose for them (the edit-user form) may do nothing but change it: every page
 * sends them to the profile page, every API call answers 403 `password_change_required`. What stays open is the way out of this state —
 * the profile page, the password form itself, and signing out.
 */
class EnsurePasswordIsChanged
{
    public const MESSAGE = 'ผู้ดูแลระบบเป็นผู้ตั้งรหัสผ่านให้คุณ กรุณาตั้งรหัสผ่านใหม่ก่อนใช้งานระบบ';

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('profile.edit', 'profile.update', 'password.update', 'logout', 'auth.logout', 'auth.logout-all')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => self::MESSAGE, 'code' => 'password_change_required'], 403);
        }

        return redirect()->route('profile.edit')->with('toast', Toast::warning(self::MESSAGE, 6000));
    }
}
