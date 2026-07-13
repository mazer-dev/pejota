<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table) {
            $table->string('channel')->default('web');
            $table->string('whatsapp_number')->nullable();
            $table->string('whatsapp_jid')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->index(['company_id', 'channel', 'whatsapp_number', 'closed_at'], 'assistant_conversations_whatsapp_session_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table) {
            $table->dropIndex('assistant_conversations_whatsapp_session_index');
            $table->dropColumn(['channel', 'whatsapp_number', 'whatsapp_jid', 'closed_at']);
        });
    }
};
