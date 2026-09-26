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
    /** Locking / unlocking: the thread's owner and any signed-in non-member (ChatThread::canBeLockedBy). */
    protected function assertCanLock(ChatThread $thread): void
    {
        abort_unless($thread->canBeLockedBy(Auth::user()), 403, 'Forbidden');
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
