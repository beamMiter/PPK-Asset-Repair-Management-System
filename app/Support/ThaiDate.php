<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Dates as a Thai document prints them: Thai month names and the Buddhist year. The app's own locale is en, so Carbon alone would
 * print "24 September 2026"; the SLA page already shows พ.ศ., and the printed report has to agree with it.
 */
final class ThaiDate
{
    /** 24 กันยายน 2569 */
    public static function long(CarbonInterface $date): string
    {
        return $date->copy()->locale('th')->translatedFormat('j F') . ' ' . ($date->year + 543);
    }

    /** 24 ก.ย. 2569 — for a table cell, where the long month name is too wide */
    public static function short(CarbonInterface $date): string
    {
        return $date->copy()->locale('th')->translatedFormat('j M') . ' ' . ($date->year + 543);
    }

    /** ก.ย. 2569 */
    public static function monthYear(CarbonInterface $date): string
    {
        return $date->copy()->locale('th')->translatedFormat('M') . ' ' . ($date->year + 543);
    }

    /** 24/09/2569 10:00 */
    public static function numericWithTime(CarbonInterface $date): string
    {
        return $date->format('d/m/') . ($date->year + 543) . $date->format(' H:i');
    }

    /**
     * A chat message's time as a person reads it, on the THAI clock whatever the server's timezone is:
     * today "15:45", yesterday "เมื่อวาน 15:45", the last few days "วันเสาร์ 15:45", older "26 ก.ย. 2569 15:45" (a weekday alone is
     * ambiguous for a message from three weeks ago). resources/js/chat/time.js does the same for a message that arrives live.
     */
    public static function chatTime(CarbonInterface $date, ?CarbonInterface $now = null): string
    {
        $tz = 'Asia/Bangkok';
        $at = $date->copy()->timezone($tz);
        $today = ($now ?? now())->copy()->timezone($tz);
        $days = (int) $at->copy()->startOfDay()->diffInDays($today->copy()->startOfDay(), false);
        $clock = $at->format('H:i');

        return match (true) {
            $days <= 0 => $clock,   // today (or a moment ahead of this server's clock)
            $days === 1 => 'เมื่อวาน ' . $clock,
            $days <= 5 => 'วัน' . $at->copy()->locale('th')->translatedFormat('l') . ' ' . $clock,
            default => self::short($at) . ' ' . $clock,
        };
    }

    /** 24 กันยายน 2569 เวลา 10:00 น. */
    public static function longWithTime(CarbonInterface $date): string
    {
        return self::long($date) . ' เวลา ' . $date->format('H:i') . ' น.';
    }
}
