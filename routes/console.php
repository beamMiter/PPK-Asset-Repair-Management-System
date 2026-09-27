<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expired API tokens are refused whether or not they are still in the table; this only tidies the table.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// What people deleted from the chat more than chat.purge_deleted_after_days ago (30) is erased for good - at 03:10 Thai time, when nobody is on.
Schedule::command('chat:purge-deleted')->dailyAt('03:10')->timezone('Asia/Bangkok')->withoutOverlapping();

// Threads nobody has used: locked after chat.lock_idle_after_days (90) of silence, deleted after chat.delete_locked_after_days (90) locked. Thai time,
// ten minutes before the purge above (a thread deleted here is not erased yet: chat.purge_deleted_after_days later).
Schedule::command('chat:expire-idle')->dailyAt(\App\Console\Commands\ExpireIdleChat::RUNS_AT)->timezone('Asia/Bangkok')->withoutOverlapping();
