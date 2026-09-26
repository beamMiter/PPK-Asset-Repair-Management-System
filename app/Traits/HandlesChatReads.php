<?php

namespace App\Traits;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Shared chat read-tracking + lock-permission logic so the web and API
 * chat controllers stay in step (the web copy predated chat_thread_reads
 * and never advanced a read pointer or computed unread counts).
 */
trait HandlesChatReads
{
    /** Locking / unlocking: admins and the IT / repair team (ChatThread::canBeLockedBy). */
    protected function assertCanLock(ChatThread $thread): void
    {
        abort_unless($thread->canBeLockedBy(Auth::user()), 403, 'Forbidden');
    }

    /**
     * A message this person already sent in this thread with the id their page made for that attempt: the answer to a RETRY (the first try
     * reached the server, its answer did not reach the page). Null for no id, an id that is not a UUID, or a new one.
     */
    protected function messageOfAttempt(int $userId, ChatThread $thread, ?string $clientId): ?ChatMessage
    {
        if (! is_string($clientId) || ! \Illuminate\Support\Str::isUuid($clientId)) {
            return null;
        }

        return ChatMessage::withTrashed()->where('user_id', $userId)->where('client_uuid', $clientId)
            ->where('chat_thread_id', $thread->id)->with('user:id,name')->first();
    }

    /**
     * Save the message - or, if the same attempt was saved a moment ago by a second request that raced this one, return that one
     * (the unique key refuses the second insert).
     *
     * @return array{0:ChatMessage,1:bool} the message, and whether it was just created
     */
    protected function saveMessageOnce(ChatThread $thread, int $userId, string $body, ?string $clientId): array
    {
        $clientId = is_string($clientId) && \Illuminate\Support\Str::isUuid($clientId) ? $clientId : null;

        try {
            $message = $thread->messages()->create(['user_id' => $userId, 'body' => $body, 'client_uuid' => $clientId]);

            return [$message->load('user:id,name'), true];
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $existing = $this->messageOfAttempt($userId, $thread, $clientId);
            if ($existing !== null) {
                return [$existing, false];
            }

            // the same id already sent to ANOTHER thread: not a retry of this message (a page makes a fresh id for each attempt, so this is a
            // bug or a clash). Save it without the id rather than answer with a message from elsewhere - or fail.
            return [$thread->messages()->create(['user_id' => $userId, 'body' => $body])->load('user:id,name'), true];
        }
    }

    /**
     * Advance a user's "last read" pointer for a thread. Opening a thread only reads it; WRITING in it ($reappear) also brings it back
     * to the person's "กระทู้ที่มีส่วนร่วม" if they had hidden it.
     */
    protected function markThreadRead(int $userId, int $threadId, int $messageId, bool $reappear = false): void
    {
        DB::table('chat_thread_reads')->updateOrInsert(
            ['user_id' => $userId, 'chat_thread_id' => $threadId],
            [
                'last_read_message_id' => $messageId,
                'last_read_at'         => now(),
                'updated_at'           => now(),
                'created_at'           => now(),
            ] + ($reappear ? ['hidden_at' => null] : []),
        );
    }

    /**
     * Take a thread out of a person's "กระทู้ที่มีส่วนร่วม": it is read up to the last message, so it stops counting toward their badge,
     * and it comes back when they write in it (or ask for it, see showThreadAgain). Nothing is deleted, for them or anybody.
     */
    protected function hideThread(int $userId, int $threadId): void
    {
        $latest = ChatMessage::query()->where('chat_thread_id', $threadId)->max('id');

        DB::table('chat_thread_reads')->updateOrInsert(
            ['user_id' => $userId, 'chat_thread_id' => $threadId],
            [
                'hidden_at'            => now(),
                'last_read_message_id' => $latest ?: null,
                'last_read_at'         => now(),
                'updated_at'           => now(),
                'created_at'           => now(),
            ],
        );
    }

    protected function showThreadAgain(int $userId, int $threadId): void
    {
        DB::table('chat_thread_reads')
            ->where('user_id', $userId)->where('chat_thread_id', $threadId)
            ->update(['hidden_at' => null, 'updated_at' => now()]);
    }

    protected function hasHiddenThread(int $userId, int $threadId): bool
    {
        return DB::table('chat_thread_reads')
            ->where('user_id', $userId)->where('chat_thread_id', $threadId)
            ->whereNotNull('hidden_at')->exists();
    }

    /** @param array<int,int> $threadIds @return array<int,int> the ones this person has hidden */
    protected function hiddenThreadIds(int $userId, array $threadIds): array
    {
        if (empty($threadIds)) {
            return [];
        }

        return DB::table('chat_thread_reads')
            ->where('user_id', $userId)->whereIn('chat_thread_id', $threadIds)->whereNotNull('hidden_at')
            ->pluck('chat_thread_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Unread message count for a thread given the caller's read pointer. */
    protected function unreadCount(int $threadId, ?int $lastReadMessageId, int $totalMessages): int
    {
        if (! $lastReadMessageId) {
            return $totalMessages;
        }

        return ChatMessage::query()
            ->where('chat_thread_id', $threadId)
            ->where('id', '>', $lastReadMessageId)
            ->count();
    }

    /**
     * [chat_thread_id => unread messages] for several threads at once (only those with any): what is newer than the person's read
     * pointer, the same meaning as unreadCount() above, in one query for a whole page of the list.
     *
     * @param  array<int,int>  $threadIds
     * @return array<int,int>
     */
    protected function unreadCountsFor(int $userId, array $threadIds): array
    {
        if (empty($threadIds)) {
            return [];
        }

        return DB::table('chat_messages as m')
            ->leftJoin('chat_thread_reads as r', function ($join) use ($userId) {
                $join->on('r.chat_thread_id', '=', 'm.chat_thread_id')->where('r.user_id', '=', $userId);
            })
            ->whereIn('m.chat_thread_id', $threadIds)
            ->whereNull('m.deleted_at')
            ->whereRaw('m.id > COALESCE(r.last_read_message_id, 0)')
            ->groupBy('m.chat_thread_id')
            ->selectRaw('m.chat_thread_id, COUNT(*) AS unread')
            ->pluck('unread', 'm.chat_thread_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** [chat_thread_id => last_read_message_id] for the given user + threads. */
    protected function readPointers(int $userId, array $threadIds): array
    {
        if (empty($threadIds)) {
            return [];
        }

        return DB::table('chat_thread_reads')
            ->where('user_id', $userId)
            ->whereIn('chat_thread_id', $threadIds)
            ->pluck('last_read_message_id', 'chat_thread_id')
            ->all();
    }
}
