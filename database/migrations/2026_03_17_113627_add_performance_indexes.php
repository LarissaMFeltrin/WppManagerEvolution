<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['chat_id', 'timestamp'], 'idx_messages_chat_timestamp');
        });

        Schema::table('conversas', function (Blueprint $table) {
            $table->index(['account_id', 'status'], 'idx_conversas_account_status');
            $table->index(['chat_id', 'status'], 'idx_conversas_chat_status');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_chat_timestamp');
        });

        Schema::table('conversas', function (Blueprint $table) {
            $table->dropIndex('idx_conversas_account_status');
            $table->dropIndex('idx_conversas_chat_status');
        });
    }
};
