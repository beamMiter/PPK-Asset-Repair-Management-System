<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

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
