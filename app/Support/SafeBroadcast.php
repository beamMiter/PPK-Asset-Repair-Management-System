<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Real-time push is an extra on top of a change that is already saved, so a push failure must never turn a successful
 * request into an error (the record exists — the user would retry and create a duplicate). Failures are logged for
 * whoever runs the server and swallowed.
 */
final class SafeBroadcast
{
    /** @return bool whether the event was handed to the broadcaster without an error */
    public static function send(object $event): bool
    {
        try {
            broadcast($event); // the broadcaster is called when this statement's PendingBroadcast is released
            return true;
        } catch (Throwable $e) {
            Log::warning('[realtime] broadcast failed — the change itself was saved', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
