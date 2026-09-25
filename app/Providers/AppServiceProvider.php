<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // The rule for a password a person chooses (sign-up, reset, changing their own): the browser pages asked for 8 characters
        // of anything and the API for 8 with a letter and a digit. Now one rule for both. (A password an admin sets for someone
        // else is checked by the admin form itself.)
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // The e-mailed link opens this app's own reset page; a separate SPA can still take over by setting
        // APP_FRONTEND_URL (config `app.frontend_url`).
        // The address is APP_URL, never the host the request came in on: `route()` builds from the Host header, so a request that
        // named another host ("Host: evil.test") got the victim a genuine e-mail whose link led to evil.test — and handed over the
        // reset token.
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $email = $notifiable->getEmailForPasswordReset();

            return config('app.frontend_url')
                ? rtrim(config('app.frontend_url'), '/')."/password-reset/$token?email=".urlencode($email)
                : rtrim((string) config('app.url'), '/').route('password.reset', ['token' => $token, 'email' => $email], false);
        });

        // Ceilings per address on the browser's public forms, for a flood of requests. They are generous on purpose — a whole ward
        // signs in behind one address at the start of a shift — and the guessing of passwords has its own, tighter counters
        // (App\Services\LoginAttempt: wrong tries only).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(60)->by((string) $request->ip()));
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(5)->by((string) $request->ip()),
            Limit::perHour(30)->by((string) $request->ip()),
        ]);
        RateLimiter::for('password-recovery', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));

        if (app()->isLocal()) {
            Response::macro('prettyJson', function ($value, int $status = 200, array $headers = []) {
                return response()->json(
                    $value,
                    $status,
                    $headers,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            });
        }
    }
}
