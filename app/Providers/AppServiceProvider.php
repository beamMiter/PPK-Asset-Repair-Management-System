<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {

        Gate::define('tech-only', fn($user) => in_array($user->role, ['technician','admin'], true));
        Gate::define('admin-only', fn($user) => $user->role === 'admin');
        // The e-mailed link opens this app's own reset page; a separate SPA can still take over by setting
        // APP_FRONTEND_URL (config `app.frontend_url`).
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $email = $notifiable->getEmailForPasswordReset();

            return config('app.frontend_url')
                ? rtrim(config('app.frontend_url'), '/')."/password-reset/$token?email=".urlencode($email)
                : route('password.reset', ['token' => $token, 'email' => $email]);
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
