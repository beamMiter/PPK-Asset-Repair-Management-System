<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expired API tokens are refused whether or not they are still in the table; this only tidies the table.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
