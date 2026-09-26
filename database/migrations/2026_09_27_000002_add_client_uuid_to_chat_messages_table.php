<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A message is sent with an id the sender's page makes for that attempt (a UUID). If the connection drops after the server saved it and
 * before the answer arrived, the page sends it again with the same id, and the server answers with the message it already has instead of
 * saving it twice (an idempotency key). Unique for one sender; messages sent without one (a plain form, older clients) are null.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->char('client_uuid', 36)->nullable()->after('body');
            $table->unique(['user_id', 'client_uuid'], 'chat_messages_user_client_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropUnique('chat_messages_user_client_uuid_unique');
            $table->dropColumn('client_uuid');
        });
    }
};
