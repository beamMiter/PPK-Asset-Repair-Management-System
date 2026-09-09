<?php

namespace App\Traits;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Shared chat read-tracking + lock-permission logic so the web and API
 * chat controllers stay in step (the web copy predated chat_thread_reads
 * and never advanced a read pointer or computed unread counts).
 */
trait HandlesChatReads
{
    /** Locking / moderation is open to any signed-in non-member. */
    protected function assertCanManageThread(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->role === 'member', 403, 'Forbidden');
    }

    /** Advance a user's "last read" pointer for a thread. */
    protected function markThreadRead(int $userId, int $threadId, int $messageId): void
    {
        DB::table('chat_thread_reads')->updateOrInsert(
            ['user_id' => $userId, 'chat_thread_id' => $threadId],
            [
                'last_read_message_id' => $messageId,
                'last_read_at'         => now(),
                'updated_at'           => now(),
                'created_at'           => now(),
            ],
        );
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
