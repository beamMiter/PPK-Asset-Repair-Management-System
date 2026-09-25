<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends the ways a person is still signed in, for when the password changes. A password reset is what someone does when they
 * think another person got in; if the other person's browser session, "remember me" cookie or API token stayed valid the
 * reset would change nothing for them.
 */
final class ActiveLogins
{
    /**
     * Every API token, every "remember me" cookie and — when sessions are kept in the database — every browser session of the
     * user, except the one named (the person changing their own password stays signed in on the device they are using).
     */
    public static function endAll(User $user, ?string $exceptSessionId = null): void
    {
        $user->tokens()->delete();

        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
                ->delete();
        }
    }
}
