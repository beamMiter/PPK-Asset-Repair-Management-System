<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;

/**
 * The two halves of "I forgot my password", for the browser pages and the API alike (they used to carry a copy each, with
 * different password rules and different wording).
 */
final class PasswordRecovery
{
    /**
     * Ask for a link. The answer to the person asking must not depend on whether the address has an account — that would list
     * every address that does — so nothing here is returned, and the sending happens after the response has gone: the time
     * taken (a mail is slower than nothing) does not say either.
     */
    public static function requestLink(string $email): void
    {
        app()->terminating(function () use ($email) {
            try {
                Password::sendResetLink(['email' => $email]);
            } catch (\Throwable $e) {
                report($e);   // the person already has their answer; a broken mail server is for us to see, not for them to learn from
            }
        });
    }

    /** Set the new password from an e-mailed token. Returns the broker's status; on success every other way in is ended. */
    public static function reset(array $credentials): string
    {
        return Password::reset($credentials, function (User $user, string $password) {
            // the `hashed` cast hashes it; a password chosen through the e-mail link is the person's own, whatever it was before
            $user->forceFill(['password' => $password, 'must_change_password' => false])->save();

            ActiveLogins::endAll($user);

            event(new PasswordReset($user));
        });
    }
}
