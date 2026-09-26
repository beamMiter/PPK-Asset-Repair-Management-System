<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** A thread was deleted (hidden) by an admin: whoever has it open is told, and taken back to the list. */
class ChatThreadDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $threadId)
    {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.' . $this->threadId)];
    }

    public function broadcastAs(): string
    {
        return 'thread.deleted';
    }

    /** @return array{thread_id:int} */
    public function broadcastWith(): array
    {
        return ['thread_id' => $this->threadId];
    }
}
