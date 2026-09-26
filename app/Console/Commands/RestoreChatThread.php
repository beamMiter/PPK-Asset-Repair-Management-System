<?php

namespace App\Console\Commands;

use App\Models\ChatModerationLog;
use App\Models\ChatThread;
use Illuminate\Console\Command;

/** Brings back a deleted thread, while it is still there (before chat:purge-deleted erases it). */
class RestoreChatThread extends Command
{
    protected $signature = 'chat:restore {thread : the thread\'s id}';

    protected $description = 'Bring back a deleted chat thread that has not been purged yet';

    public function handle(): int
    {
        $thread = ChatThread::onlyTrashed()->find((int) $this->argument('thread'));

        if (! $thread) {
            $this->error('There is no deleted thread with that id (it may have been purged, or it was never deleted).');

            return self::FAILURE;
        }

        $thread->restore();
        ChatModerationLog::record(ChatModerationLog::RESTORE_THREAD, null, $thread, meta: ['by' => 'artisan']);

        $this->info("Thread #{$thread->id} \"{$thread->title}\" is back.");

        return self::SUCCESS;
    }
}
