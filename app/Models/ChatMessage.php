<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'chat_thread_id',
        'user_id',
        'body',
        'client_uuid',
    ];

    protected $touches = ['thread'];

    protected $casts = [
        'chat_thread_id' => 'integer',
        'user_id'        => 'integer',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'chat_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeInThread(Builder $q, int $threadId): Builder
    {
        return $q->where('chat_thread_id', $threadId);
    }

    public function scopeLatestFirst(Builder $q): Builder
    {
        return $q->orderByDesc('created_at');
    }

    public function scopeAfterId(Builder $q, int $afterId): Builder
    {
        return $q->where('id', '>', $afterId);
    }

    /**
     * What a page draws a message from (an older batch, a reply): a deleted message carries no words, only that it is deleted.
     *
     * @return array{id:int,chat_thread_id:int,user_id:?int,body:?string,deleted:bool,created_at:?string,user:?array{id:int,name:string,avatar_thumb_url:?string}}
     */
    public function toChatArray(): array
    {
        $deleted = $this->trashed();

        return [
            'id' => (int) $this->id,
            'chat_thread_id' => (int) $this->chat_thread_id,
            'user_id' => $this->user_id ? (int) $this->user_id : null,
            'body' => $deleted ? null : $this->body,
            'deleted' => $deleted,
            'created_at' => $this->created_at?->toISOString(),
            'user' => $this->user ? [
                'id' => (int) $this->user->id,
                'name' => $this->user->name,
                'avatar_thumb_url' => $this->user->avatar_thumb_url,
            ] : null,
        ];
    }
}
