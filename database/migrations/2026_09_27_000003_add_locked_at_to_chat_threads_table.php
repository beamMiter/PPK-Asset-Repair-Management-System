<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a thread was locked. A thread that stays locked for `chat.delete_locked_after_days` is deleted by the nightly `chat:expire-idle`, and
 * the clock has to start when it was locked - not when it was last written in, which is what updated_at says.
 *
 * The threads that are locked today start their clock at the moment of this migration, not at the (unknown) day they were locked: nothing
 * that was locked long ago is deleted the first night, everybody gets the whole period - and the notice in the thread - first.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_threads', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('is_locked');
        });

        DB::table('chat_threads')->where('is_locked', true)->update(['locked_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('chat_threads', function (Blueprint $table) {
            $table->dropColumn('locked_at');
        });
    }
};
