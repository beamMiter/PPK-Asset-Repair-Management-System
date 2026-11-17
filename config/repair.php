<?php

/**
 * The repair requests' own rules that a hospital may want to tune. Every number can be changed from the environment.
 */
return [
    // A request the staff SENT BACK to its reporter to fix ("ส่งกลับแก้ไข") waits this many days (Thai calendar days: sent back on the 2nd, it has
    // 14 days left that day and is cancelled by the 03:30 sweep of the 16th). If the reporter has not fixed and sent it again by then, the
    // nightly `repair:expire-returned` cancels it, so a request nobody comes back to does not sit in the system for ever. 0 = never.
    'return_expires_after_days' => (int) env('REPAIR_RETURN_EXPIRES_AFTER_DAYS', 14),

    // "Close to expiring": this many days or fewer left. It colours the countdown and is what the list's "ส่งกลับแก้ไข — ใกล้หมดอายุ" choice shows.
    'return_warning_days' => (int) env('REPAIR_RETURN_WARNING_DAYS', 3),
];
