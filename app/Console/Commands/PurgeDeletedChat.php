<?php

namespace App\Console\Commands;

use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\ChatThread;
use Illuminate\Console\Command;

/**
 * Clears the chat's rubbish: a thread that somebody deleted, and a message that somebody deleted, is only HIDDEN when it is deleted (so it
 * can be brought back); once it has been deleted for `chat.purge_deleted_after_days` (30 by default) it is erased for good - the thread with
 * its messages and read marks - because a deleted conversation that stays in the database forever is personal data kept for no reason.
 * A thread's title and the count of what was erased are written to the moderation record, never the words.
 *
 * Runs every night (routes/console.php). `--dry-run` says what it would erase and erases nothing. Backups still hold what they held.
 */
class PurgeDeletedChat extends Command
{
    protected $signature = 'chat:purge-deleted {--days= : erase what has been deleted for at least this many days (default: config chat.purge_deleted_after_days)} {--dry-run : only say what would be erased}';

    protected $description = 'Erase chat threads and messages that were deleted long enough ago to leave no way back';

    public function handle(): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : (int) config('chat.purge_deleted_after_days');

        if ($days <= 0) {
            $this->info('Purging is off (chat.purge_deleted_after_days is 0).');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $dry = (bool) $this->option('dry-run');
        $threads = 0;
        $messages = 0;

        // whole threads first (their messages and read marks go with them, by the foreign keys)
        ChatThread::onlyTrashed()->where('deleted_at', '<', $cutoff)->chunkById(100, function ($chunk) use ($dry, &$threads, &$messages) {
            foreach ($chunk as $thread) {
                $count = ChatMessage::withTrashed()->where('chat_thread_id', $thread->id)->count();
                $threads++;
                $messages += $count;

                if ($dry) {
                    continue;
                }

                ChatModerationLog::record(ChatModerationLog::PURGE_THREAD, null, $thread, meta: [
                    'messages_erased' => $count,
                    'deleted_at' => $thread->deleted_at?->toIso8601String(),
                ]);
                $thread->forceDelete();
            }
        });

        // then single messages deleted from threads that still exist
        $lone = ChatMessage::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $loneCount = (clone $lone)->count();
        if (! $dry) {
            $lone->forceDelete();
        }
        $messages += $loneCount;

        $verb = $dry ? 'would erase' : 'erased';
        $this->info("chat:purge-deleted {$verb} {$threads} thread(s) and {$messages} message(s) deleted more than {$days} days ago.");

        return self::SUCCESS;
    }
}
