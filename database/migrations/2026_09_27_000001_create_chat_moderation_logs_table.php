<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who locked, unlocked or deleted what in the chat, and when (OWASP Logging: security-relevant administrative actions are recorded). The
 * thread and message ids are plain numbers, not foreign keys: the record has to outlive a thread that is purged later. `meta` holds what
 * is needed to read it afterwards (the thread's title, whether it was somebody's own message) and no message text.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();   // null: the system (the purge)
            $table->string('action', 40);                                                      // lock, unlock, delete_thread, delete_message, purge_thread
            $table->unsignedBigInteger('chat_thread_id')->nullable();
            $table->unsignedBigInteger('chat_message_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['chat_thread_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_moderation_logs');
    }
};
