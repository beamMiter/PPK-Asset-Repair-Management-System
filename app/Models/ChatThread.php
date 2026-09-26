<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
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
     * Threads a person took part in: they started it, or they have written in it. The chat page's "กระทู้ที่มีส่วนร่วม" tab and the
     * floating widget's list of the same name both mean this.
     */
    public function scopeInvolving(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('author_id', $userId)
                ->orWhereHas('messages', fn ($messages) => $messages->where('user_id', $userId));
        });
    }

    /**
     * Locking and unlocking a thread is moderation, so it is for staff: admins, supervisors, IT support and technicians - anybody but a
     * plain member, the thread's own author included (an author may delete their thread, or hide it, but not overrule a moderator by
     * unlocking one that staff locked).
     */
    public function canBeLockedBy(?User $user): bool
    {
        return $user !== null && $user->role !== 'member';
    }

    /**
     * Deleting a thread (it is hidden from everyone) belongs to whoever started it, and to an admin. Everybody else who took part
     * hides it from their own list instead (see scopeInMyList).
     */
    public function canBeDeletedBy(?User $user): bool
    {
        return $user !== null && ($user->role === 'admin' || (int) $user->id === (int) $this->author_id);
    }

    /**
     * "กระทู้ที่มีส่วนร่วม" as a person sees it: the threads they took part in, minus the ones they hid (chat_thread_reads.hidden_at).
     * The chat page's tab, its count and the API's `scope=mine` are this.
     */
    public function scopeInMyList(Builder $query, int $userId): Builder
    {
        return $query->involving($userId)->whereNotExists(function ($hidden) use ($userId) {
            $hidden->select(DB::raw(1))->from('chat_thread_reads')
                ->whereColumn('chat_thread_reads.chat_thread_id', 'chat_threads.id')
                ->where('chat_thread_reads.user_id', $userId)
                ->whereNotNull('chat_thread_reads.hidden_at');
        });
    }

    /**
     * What the floating widget lists: their list, minus a locked thread they have read to the end. Nobody can add to a locked thread,
     * so there is nothing in it to catch up on; it stays in the page's tab, and it is back in the widget when it is unlocked.
     */
    public function scopeForTheWidget(Builder $query, int $userId): Builder
    {
        return $query->inMyList($userId)->where(function (Builder $q) use ($userId) {
            $q->where('chat_threads.is_locked', false)->orWhereExists(function ($unread) use ($userId) {
                $unread->select(DB::raw(1))->from('chat_messages')
                    ->whereColumn('chat_messages.chat_thread_id', 'chat_threads.id')
                    ->whereNull('chat_messages.deleted_at')
                    ->whereRaw(
                        'chat_messages.id > COALESCE((SELECT r.last_read_message_id FROM chat_thread_reads r WHERE r.user_id = ? AND r.chat_thread_id = chat_threads.id), 0)',
                        [$userId],
                    );
            });
        });
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'chat_thread_id')->latestOfMany('created_at');
    }
}
