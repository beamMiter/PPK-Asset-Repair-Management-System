<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A thread was locked or unlocked. Everybody who has it open hears it on the thread's own channel and the composer changes
 * on their page, with no refresh (resources/js/chat/page.js). The polling fallback carries the same fact in a header.
 */
class ChatThreadLockChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $threadId, public bool $isLocked)
    {
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('chat.' . $this->threadId)];
    }

    public function broadcastAs(): string
    {
        return 'thread.lock';
    }

    /** @return array{thread_id:int,is_locked:bool} */
    public function broadcastWith(): array
    {
        return ['thread_id' => $this->threadId, 'is_locked' => $this->isLocked];
    }
}
