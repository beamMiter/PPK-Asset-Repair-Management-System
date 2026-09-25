<?php

namespace App\Support;

use Illuminate\Support\Facades\Password;

/**
 * Thai wording for password recovery, independent of `APP_LOCALE` (the rest of the UI is Thai-only, and the framework's own
 * strings are English unless a `lang/<locale>/passwords.php` exists for the active locale).
 *
 * What is NOT here on purpose: "no account uses this e-mail". Said to whoever asks, it lists every address that has one.
 */
final class PasswordResetMessage
{
    /** The answer to "send me a link", the same whether or not the address has an account. */
    public static function linkRequested(): string
    {
        return 'หากอีเมลนี้มีบัญชีอยู่ในระบบ จะได้รับลิงก์ตั้งรหัสผ่านใหม่ทางอีเมลภายในไม่กี่นาที (บัญชีที่ไม่มีอีเมลให้ติดต่อผู้ดูแลระบบ)';
    }

    public static function resetDone(): string
    {
        return 'ตั้งรหัสผ่านใหม่เรียบร้อยแล้ว';
    }

    /** Why a reset was refused. A wrong token and an unknown address read the same: told apart, they list the addresses. */
    public static function resetRefused(string $status): string
    {
        return match ($status) {
            Password::RESET_THROTTLED => 'กรุณารอสักครู่ก่อนลองอีกครั้ง',
            default => 'ลิงก์ตั้งรหัสผ่านไม่ถูกต้องหรือหมดอายุแล้ว',
        };
    }
}
