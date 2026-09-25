<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatThread extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'author_id', 'is_locked'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_thread_id');
    }

    /**
     * Threads a person took part in: they started it, or they have written in it. The chat page's "ที่ฉันมีส่วนร่วม" list and the
     * floating widget's "กระทู้ของฉัน" both mean this.
     */
    public function scopeInvolving(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('author_id', $userId)
                ->orWhereHas('messages', fn ($messages) => $messages->where('user_id', $userId));
        });
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'chat_thread_id')->latestOfMany('created_at');
    }
}
