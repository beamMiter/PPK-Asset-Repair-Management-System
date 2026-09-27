<?php

namespace App\Models;

use App\Console\Commands\ExpireIdleChat;
use Carbon\CarbonImmutable;
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

    protected $casts = ['is_locked' => 'boolean', 'locked_at' => 'datetime'];

    protected static function booted(): void
    {
        // When a thread was locked is written wherever it is locked (a moderator, the API, the nightly sweep, a seeder), so no caller can forget:
        // the "locked for N days, then deleted" clock starts here, and unlocking stops it. A caller that sets locked_at itself keeps its value.
        static::saving(function (self $thread) {
            if ($thread->isDirty('is_locked') && ! $thread->isDirty('locked_at')) {
                if ($thread->is_locked) {
                    $thread->locked_at = now();
                } elseif ($thread->locked_at !== null) {
                    $thread->locked_at = null;   // (a new open thread has nothing to write: it does not need the column)
                }
            }
        });
    }

    /**
     * The day the nightly sweep will lock this open thread if nobody writes in it: the first run after `lock_idle_after_days` of silence. Null
     * when the thread is locked already, or the rule is off. (updated_at is the last word: a message, or a moderator's lock / unlock.)
     */
    public function autoLocksOn(): ?CarbonImmutable
    {
        $days = (int) config('chat.lock_idle_after_days');

        if ($days <= 0 || $this->is_locked || $this->updated_at === null) {
            return null;
        }

        return self::sweepDayAfter(CarbonImmutable::instance($this->updated_at)->addDays($days));
    }

    /** The day the nightly sweep will delete this LOCKED thread if nobody unlocks it. Null when it is open, or the rule is off. */
    public function autoDeletesOn(): ?CarbonImmutable
    {
        $days = (int) config('chat.delete_locked_after_days');

        if ($days <= 0 || ! $this->is_locked || $this->locked_at === null) {
            return null;
        }

        return self::sweepDayAfter(CarbonImmutable::instance($this->locked_at)->addDays($days));
    }

    /** The lock date, only in the last `warn_days_before` days before it - what an open thread warns with. */
    public function autoLockWarningOn(): ?CarbonImmutable
    {
        $on = $this->autoLocksOn();

        return $on !== null && $on->lte(now()->timezone('Asia/Bangkok')->addDays((int) config('chat.warn_days_before'))->startOfDay()) ? $on : null;
    }

    /** What the chat tells everybody about how long a thread lives (the create dialog), or null when neither rule is on. */
    public static function lifecycleNotice(): ?string
    {
        $lock = (int) config('chat.lock_idle_after_days');
        $delete = (int) config('chat.delete_locked_after_days');
        $parts = [];

        if ($lock > 0) {
            $parts[] = "กระทู้ที่ไม่มีการตอบครบ {$lock} วันจะถูกล็อกอัตโนมัติ";
        }
        if ($delete > 0) {
            $parts[] = "กระทู้ที่ถูกล็อกครบ {$delete} วันโดยไม่มีการปลดล็อกจะถูกลบอัตโนมัติ";
        }

        return $parts ? implode(' และ', $parts) : null;
    }

    /** The sweep runs at 03:00 Thai time: a thread whose limit passes at 14:00 on the 5th is dealt with on the morning of the 6th. */
    private static function sweepDayAfter(CarbonImmutable $limit): CarbonImmutable
    {
        $at = $limit->timezone('Asia/Bangkok');
        [$h, $m] = array_map('intval', explode(':', ExpireIdleChat::RUNS_AT));
        $run = $at->setTime($h, $m);

        return ($run->gt($at) ? $run : $run->addDay())->startOfDay();
    }

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
     * Locking and unlocking a thread is moderation: admins and the IT / repair team (User::workerRoles - IT support, network,
     * programmers, technicians). Not a supervisor, and not a plain member, the thread's own author included (an author may delete their
     * thread, or hide it, but not overrule a moderator by unlocking one that staff locked).
     */
    public function canBeLockedBy(?User $user): bool
    {
        return $user !== null && ($user->role === User::ROLE_ADMIN || in_array($user->role, User::workerRoles(), true));
    }

    /**
     * Deleting one message: whoever wrote it (while the thread is open - nobody changes a closed thread's history but a moderator), and
     * a moderator - the people who may lock: admins and the IT / repair team.
     */
    public function canDeleteMessage(ChatMessage $message, ?User $user): bool
    {
        if ($user === null || (int) $message->chat_thread_id !== (int) $this->id) {
            return false;
        }

        return $this->canBeLockedBy($user) || ((int) $message->user_id === (int) $user->id && ! $this->is_locked);
    }

    /**
     * Editing a message: only the person who wrote it, and only while the thread is open (a closed thread's history is not changed by anybody).
     * A moderator may delete somebody's message but not rewrite it - putting other words under a person's name is not moderation.
     */
    public function canEditMessage(ChatMessage $message, ?User $user): bool
    {
        return $user !== null
            && (int) $message->chat_thread_id === (int) $this->id
            && ! $message->trashed()
            && ! $this->is_locked
            && (int) $message->user_id === (int) $user->id;
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

    /** The threads a person has hidden from their "กระทู้ที่มีส่วนร่วม" (the "ซ่อนไว้" list). */
    public function scopeHiddenBy(Builder $query, int $userId): Builder
    {
        return $query->whereExists(function ($hidden) use ($userId) {
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
