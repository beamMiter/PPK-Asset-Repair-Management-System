<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** One message of a thread was deleted (its author, or a moderator): whoever has the thread open replaces it with "ข้อความนี้ถูกลบ". */
class ChatMessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $threadId, public int $messageId)
    {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.' . $this->threadId)];
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    /** @return array{thread_id:int,message_id:int} */
    public function broadcastWith(): array
    {
        return ['thread_id' => $this->threadId, 'message_id' => $this->messageId];
    }
}
