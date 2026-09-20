<?php

namespace App\Support;

use Illuminate\Support\Facades\Password;

/**
 * Thai wording for the password-broker statuses, independent of `APP_LOCALE` (the rest of the UI is Thai-only, and
 * the framework's own strings are English unless a `lang/<locale>/passwords.php` exists for the active locale).
 */
final class PasswordResetMessage
{
    public static function for(string $status): string
    {
        return match ($status) {
            Password::RESET_LINK_SENT => 'ส่งลิงก์ตั้งรหัสผ่านใหม่ไปที่อีเมลแล้ว',
            Password::PASSWORD_RESET => 'ตั้งรหัสผ่านใหม่เรียบร้อยแล้ว',
            Password::RESET_THROTTLED => 'กรุณารอสักครู่ก่อนลองอีกครั้ง',
            Password::INVALID_TOKEN => 'ลิงก์ตั้งรหัสผ่านไม่ถูกต้องหรือหมดอายุแล้ว',
            Password::INVALID_USER => 'ไม่พบบัญชีที่ใช้อีเมลนี้ — หากบัญชีของคุณไม่มีอีเมล ให้ติดต่อผู้ดูแลระบบเพื่อตั้งรหัสผ่านใหม่',
            default => __($status),
        };
    }
}
