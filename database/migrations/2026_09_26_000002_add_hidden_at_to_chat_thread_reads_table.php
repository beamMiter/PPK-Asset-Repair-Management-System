<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person can hide a thread from their own "กระทู้ที่มีส่วนร่วม" (the chat page's tab and the floating widget) without deleting it
 * for anybody: `hidden_at` on their read pointer for that thread. It is cleared when they write in the thread again. Existing rows
 * are not hidden (null).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_thread_reads', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('last_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_thread_reads', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });
    }
};
