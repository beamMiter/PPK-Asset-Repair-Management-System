<?php

namespace App\Console\Commands;

use App\Events\Chat\ChatThreadDeleted;
use App\Events\Chat\ChatThreadLockChanged;
use App\Models\ChatModerationLog;
use App\Models\ChatThread;
use App\Support\SafeBroadcast;
use Illuminate\Console\Command;

/**
 * The chat does not keep a conversation for ever (PDPA: personal data with no reason to be kept). Every night:
 *
 *  1. a thread nobody has written in, or unlocked, for `chat.lock_idle_after_days` (90) is LOCKED - nobody can add to it, a moderator can
 *     open it again, and that restarts the count;
 *  2. a thread that has stayed locked for `chat.delete_locked_after_days` (90) is DELETED - hidden, like any deleted thread, and erased for
 *     good `chat.purge_deleted_after_days` (30) later by chat:purge-deleted; until then `php artisan chat:restore {id}` brings it back.
 *
 * Both are written to the moderation record with no actor (the system). A locked thread does not jump to the top of anybody's list (the
 * sweep does not touch its updated_at), and the people who have it open see it lock / disappear at once (the same broadcasts as by hand).
 * Each rule is off at 0. `--dry-run` says what it would do and does nothing. The thread itself says when it will happen (chat page, API).
 */
class ExpireIdleChat extends Command
{
    /** Thai time (Asia/Bangkok), when nobody is on: routes/console.php schedules it, and the dates a thread announces follow it. */
    public const RUNS_AT = '03:00';

    protected $signature = 'chat:expire-idle {--dry-run : only say what would be locked and deleted}';

    protected $description = 'Lock chat threads nobody has used for months, and delete the ones that stayed locked';

    public function handle(): int
    {
        $lockDays = (int) config('chat.lock_idle_after_days');
        $deleteDays = (int) config('chat.delete_locked_after_days');
        $dry = (bool) $this->option('dry-run');
        $deleted = 0;
        $locked = 0;

        // deletion first: what this run locks is not old enough to delete, but the order keeps the two counts easy to read
        if ($deleteDays > 0) {
            ChatThread::query()->where('is_locked', true)->whereNotNull('locked_at')->where('locked_at', '<', now()->subDays($deleteDays))
                ->chunkById(100, function ($chunk) use ($dry, $deleteDays, &$deleted) {
                    foreach ($chunk as $thread) {
                        $deleted++;

                        if ($dry) {
                            continue;
                        }

                        ChatModerationLog::record(ChatModerationLog::AUTO_DELETE, null, $thread, meta: [
                            'locked_at' => $thread->locked_at?->toIso8601String(),
                            'locked_for_days' => $deleteDays,
                        ]);
                        $thread->delete();
                        SafeBroadcast::send(new ChatThreadDeleted((int) $thread->id));
                    }
                });
        }

        if ($lockDays > 0) {
            ChatThread::query()->where('is_locked', false)->where('updated_at', '<', now()->subDays($lockDays))
                ->chunkById(100, function ($chunk) use ($dry, $lockDays, &$locked) {
                    foreach ($chunk as $thread) {
                        $locked++;

                        if ($dry) {
                            continue;
                        }

                        $lastWord = $thread->updated_at?->toIso8601String();
                        $thread->timestamps = false;   // not a new word: keeps its place in every list, and the day the silence began
                        $thread->is_locked = true;     // locked_at is set by the model
                        $thread->save();

                        ChatModerationLog::record(ChatModerationLog::AUTO_LOCK, null, $thread, meta: [
                            'last_activity_at' => $lastWord,
                            'idle_days' => $lockDays,
                        ]);
                        SafeBroadcast::send(new ChatThreadLockChanged((int) $thread->id, true));
                    }
                });
        }

        $verb = $dry ? 'would lock' : 'locked';
        $verb2 = $dry ? 'would delete' : 'deleted';
        $this->info("chat:expire-idle {$verb} {$locked} idle thread(s) and {$verb2} {$deleted} thread(s) locked for too long.");

        return self::SUCCESS;
    }
}
