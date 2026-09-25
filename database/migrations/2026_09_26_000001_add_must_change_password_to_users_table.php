<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A password an admin chose for somebody is known to the admin, and usually written on a slip of paper: the person must replace it
 * before they use the system. The flag is set when an admin creates an account or sets somebody else's password, and cleared when the
 * person changes it (the profile page) or resets it by e-mail. Existing accounts are not touched (false).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
