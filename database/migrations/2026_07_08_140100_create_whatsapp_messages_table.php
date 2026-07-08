<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('whatsapp_conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('evolution_instance');
            $table->string('remote_message_id')->nullable();
            $table->string('remote_jid')->nullable();
            $table->string('sender_jid')->nullable();
            $table->string('sender_name')->nullable();
            $table->boolean('from_me')->default(false);
            $table->string('message_type')->default('text');
            $table->longText('text')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'evolution_instance', 'remote_message_id'], 'wa_messages_unique_remote');
            $table->index(['whatsapp_conversation_id', 'sent_at']);
            $table->index(['company_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
