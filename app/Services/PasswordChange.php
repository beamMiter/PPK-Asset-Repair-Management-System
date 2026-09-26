<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * A person changing their OWN password - the profile page and `PUT /api/auth/password` do the same thing: the new password is kept,
 * an admin's "you must change it" flag is cleared, and every other way into the account ends (another person may have had the old
 * password), while the device they are using stays signed in.
 */
final class PasswordChange
{
    /** @return bool whether they had been asked to change it (an admin had chosen it for them) */
    public static function apply(User $user, string $newPassword, ?string $keepSessionId = null, ?int $keepTokenId = null): bool
    {
        $wasForced = (bool) $user->must_change_password;

        $user->forceFill([
            'password' => Hash::make($newPassword),
            'must_change_password' => false,
        ])->save();

        ActiveLogins::endAll($user, $keepSessionId, $keepTokenId);

        return $wasForced;
    }
}
