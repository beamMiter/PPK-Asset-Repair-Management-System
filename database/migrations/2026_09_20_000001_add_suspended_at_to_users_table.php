<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts are suspended, not deleted: a user is referenced by requests, logs, assignments, ratings and chat
 * (some ON DELETE CASCADE), so removing one destroys other people's data. A suspended account keeps all of its
 * history but cannot sign in or be assigned new work; an admin can reactivate it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('suspended_at');
        });
    }
};
