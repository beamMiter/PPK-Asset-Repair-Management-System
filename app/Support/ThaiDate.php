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

    /** วันเสาร์ 15:45 - a chat message's time: the weekday and the clock, 24-hour */
    public static function weekdayTime(CarbonInterface $date): string
    {
        return 'วัน' . $date->copy()->locale('th')->translatedFormat('l H:i');   // Carbon names the day "เสาร์"; a Thai reader expects "วันเสาร์"
    }

    /** 24 กันยายน 2569 เวลา 10:00 น. */
    public static function longWithTime(CarbonInterface $date): string
    {
        return self::long($date) . ' เวลา ' . $date->format('H:i') . ' น.';
    }
}
