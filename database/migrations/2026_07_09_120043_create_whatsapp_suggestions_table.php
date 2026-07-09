<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_suggestions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('whatsapp_conversation_id')
                ->constrained('whatsapp_conversations')
                ->cascadeOnDelete();

            $table->foreignId('whatsapp_message_id')
                ->nullable()
                ->constrained('whatsapp_messages')
                ->nullOnDelete();

            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();

            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects')
                ->nullOnDelete();

            $table->string('type');
            $table->string('title');
            $table->text('content');
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();

            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tasks')
                ->nullOnDelete();

            $table->foreignId('note_id')
                ->nullable()
                ->constrained('notes')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'whatsapp_conversation_id', 'status'], 'wa_suggestions_conversation_status');
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->foreignId('last_suggested_message_id')
                ->nullable()
                ->constrained('whatsapp_messages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_suggested_message_id');
        });

        Schema::dropIfExists('whatsapp_suggestions');
    }
};
