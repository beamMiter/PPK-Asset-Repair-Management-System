<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The one place that decides whether a citizen id + password may sign in — for the browser form (LoginRequest) and for the
 * API token login (Api\AuthController) alike. They used to carry two copies that had drifted apart (different counter keys,
 * different password rules, a route limit on one and none on the other).
 *
 * Two counters of WRONG tries, so neither can be dodged by moving to the other:
 *  - per account and address: 5, then a minute's wait. Stops guessing one person's password.
 *  - per address, any account: 30 in 5 minutes. Stops "password spraying" — one likely password tried against every citizen
 *    id from one machine, which never trips the first counter. Only wrong tries count, so a whole ward signing in behind one
 *    address at the start of a shift does not lock itself out; a right password never clears this counter.
 */
final class LoginAttempt
{
    public const WRONG = 'เลขบัตรประชาชนหรือรหัสผ่านไม่ถูกต้อง';

    public const MAX_PER_ACCOUNT = 5;
    public const ACCOUNT_DECAY = 60;        // seconds
    public const MAX_PER_ADDRESS = 30;
    public const ADDRESS_DECAY = 300;       // seconds

    private function __construct(
        private readonly string $citizenId,
        private readonly string $ip,
    ) {
    }

    public static function for(string $citizenId, string $ip): self
    {
        return new self($citizenId, $ip);
    }

    public static function lockedMessage(int $seconds): string
    {
        // Says it is a temporary lock-out, not "wrong password" — otherwise a locked user keeps retrying the right password
        // and thinks sign-in is broken.
        return "พยายามเข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารอ {$seconds} วินาทีแล้วลองใหม่";
    }

    /** Seconds to wait before the next try, 0 when it may go ahead. */
    public function waitSeconds(): int
    {
        $wait = 0;

        if (RateLimiter::tooManyAttempts($this->accountKey(), self::MAX_PER_ACCOUNT)) {
            $wait = RateLimiter::availableIn($this->accountKey());
        }
        if (RateLimiter::tooManyAttempts($this->addressKey(), self::MAX_PER_ADDRESS)) {
            $wait = max($wait, RateLimiter::availableIn($this->addressKey()));
        }

        return $wait;
    }

    /** A wrong try — a wrong password, or a suspended account whose password was right. */
    public function fail(): void
    {
        RateLimiter::hit($this->accountKey(), self::ACCOUNT_DECAY);
        RateLimiter::hit($this->addressKey(), self::ADDRESS_DECAY);
    }

    public function succeed(): void
    {
        RateLimiter::clear($this->accountKey());
    }

    /** The user, when the password is right; null for a wrong password and for a citizen id nobody has. Says nothing about which. */
    public function userWithPassword(string $password): ?User
    {
        $user = User::where('citizen_id', $this->citizenId)->first();

        // One hash check whether or not the account exists: a missing account answered faster, and the time said which
        // citizen ids have one.
        $right = Hash::check($password, $user?->password ?? $this->dummyHash());

        if (! $user || ! $right) {
            return null;
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();   // the `hashed` cast hashes it with the current settings
        }

        return $user;
    }

    /** A hash made with the same settings as the real ones, so checking against it costs the same. Made once and kept. */
    private function dummyHash(): string
    {
        $settings = config('hashing.driver') . ':' . json_encode(config('hashing.' . config('hashing.driver')));

        return Cache::rememberForever('login-dummy-hash:' . md5($settings), fn () => Hash::make(Str::random(40)));
    }

    private function accountKey(): string
    {
        return 'login:' . $this->citizenId . '|' . $this->ip;
    }

    private function addressKey(): string
    {
        return 'login-address:' . $this->ip;
    }
}
