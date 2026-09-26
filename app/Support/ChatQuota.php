<?php

namespace App\Support;

use App\Models\ChatThread;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * How many threads a person may still start today. The day is the THAI calendar day (00:00 to 24:00 น.), worked out from the clock in
 * Asia/Bangkok whatever the server's timezone is; the count includes threads deleted afterwards; admins have no limit.
 */
final class ChatQuota
{
    public const TIMEZONE = 'Asia/Bangkok';

    public static function limit(): int
    {
        return max(0, (int) config('chat.threads_per_day'));
    }

    public static function unlimited(User $user): bool
    {
        return $user->role === User::ROLE_ADMIN;
    }

    /** 00:00 น. today in Thailand, as a moment in the app's own timezone (the one the database stores). */
    public static function startOfDay(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay()->setTimezone((string) config('app.timezone'));
    }

    public static function resetsAt(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay()->addDay()->setTimezone((string) config('app.timezone'));
    }

    public static function used(User $user): int
    {
        return ChatThread::withTrashed()
            ->where('author_id', $user->id)
            ->where('created_at', '>=', self::startOfDay())
            ->count();
    }

    /** @return array{unlimited:bool,limit:int,used:int,remaining:?int,resets_at:string}   remaining is null when there is no limit */
    public static function for(User $user): array
    {
        $limit = self::limit();
        $used = self::unlimited($user) ? 0 : self::used($user);

        return [
            'unlimited' => self::unlimited($user),
            'limit' => $limit,
            'used' => $used,
            'remaining' => self::unlimited($user) ? null : max(0, $limit - $used),
            'resets_at' => self::resetsAt()->toIso8601String(),
        ];
    }

    public static function canStart(User $user): bool
    {
        return self::unlimited($user) || self::used($user) < self::limit();
    }

    public static function refusal(): string
    {
        return 'วันนี้คุณตั้งกระทู้ครบ ' . self::limit() . ' ครั้งแล้ว ตั้งกระทู้ใหม่ได้ตั้งแต่ 00:00 น. ของพรุ่งนี้';
    }

    /** Seconds until the Thai midnight: what `Retry-After` says. */
    public static function secondsUntilReset(): int
    {
        return max(1, (int) CarbonImmutable::now()->diffInSeconds(self::resetsAt(), true));
    }
}
