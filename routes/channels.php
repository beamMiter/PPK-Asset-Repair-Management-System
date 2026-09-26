<?php

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// One private channel per chat thread: what its messages, its lock and its deletion are announced on. Every signed-in account that is
// not suspended may read every thread (the chat is open to all staff by design), but it has to be signed in - and a thread that no longer
// exists has no listeners. (Suspended and password-change-pending accounts are stopped before this by the route's middleware.)
Broadcast::channel('chat.{threadId}', function (User $user, int $threadId) {
    return ! $user->isSuspended() && ChatThread::whereKey($threadId)->exists();
});
