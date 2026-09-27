<?php

namespace App\Events\Chat;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** The author changed the words of a message: whoever has the thread open swaps the text in place and marks it "แก้ไขแล้ว". */
class ChatMessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $threadId, public int $messageId, public string $body, public ?string $editedAt)
    {
    }

    public static function of(ChatMessage $message): self
    {
        return new self((int) $message->chat_thread_id, (int) $message->id, (string) $message->body, $message->edited_at?->toISOString());
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.' . $this->threadId)];
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    /** @return array{thread_id:int,message_id:int,body:string,edited_at:?string} */
    public function broadcastWith(): array
    {
        return ['thread_id' => $this->threadId, 'message_id' => $this->messageId, 'body' => $this->body, 'edited_at' => $this->editedAt];
    }
}
