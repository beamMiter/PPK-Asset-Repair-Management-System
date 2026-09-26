<?php

/**
 * Limits on the chat. Every number can be changed from the environment; the defaults are for a hospital's own staff, not the public.
 */
return [
    // What a person may SEND: against a flood, not against spam (nothing reads the words). A burst is stopped in seconds, a steady stream
    // over minutes - a person writing normally is never near either.
    'message_burst_max' => (int) env('CHAT_MESSAGE_BURST_MAX', 8),
    'message_burst_seconds' => (int) env('CHAT_MESSAGE_BURST_SECONDS', 10),
    'message_sustained_max' => (int) env('CHAT_MESSAGE_SUSTAINED_MAX', 60),
    'message_sustained_seconds' => (int) env('CHAT_MESSAGE_SUSTAINED_SECONDS', 300),

    // New threads: this many a day for each person (the day is the Thai calendar day, from 00:00 น., whatever the server's clock says;
    // a thread deleted afterwards still counts, so making and deleting is no way round it). Admins are not counted. A double click that
    // makes the same thread twice is stopped by the short limit.
    'threads_per_day' => (int) env('CHAT_THREADS_PER_DAY', 5),
    'thread_burst_max' => (int) env('CHAT_THREAD_BURST_MAX', 3),
    'thread_burst_seconds' => (int) env('CHAT_THREAD_BURST_SECONDS', 60),
];
