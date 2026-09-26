<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/** One line of the chat's moderation record: who did what to which thread or message, from where, when. */
class ChatModerationLog extends Model
{
    public const LOCK = 'lock';
    public const UNLOCK = 'unlock';
    public const DELETE_THREAD = 'delete_thread';
    public const DELETE_MESSAGE = 'delete_message';
    public const PURGE_THREAD = 'purge_thread';

    public $timestamps = false;

    protected $fillable = ['actor_id', 'action', 'chat_thread_id', 'chat_message_id', 'meta', 'ip', 'created_at'];

    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @param array<string,mixed> $meta */
    public static function record(string $action, ?User $actor, ChatThread $thread, ?ChatMessage $message = null, array $meta = [], ?Request $request = null): self
    {
        return self::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'chat_thread_id' => $thread->id,
            'chat_message_id' => $message?->id,
            'meta' => ['thread_title' => $thread->title] + $meta,
            'ip' => $request?->ip(),
            'created_at' => now(),
        ]);
    }
}
