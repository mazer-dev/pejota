<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('whatsapp_message_id')->constrained('whatsapp_messages')->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('extension')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('sha256')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->longText('transcription_text')->nullable();
            $table->string('status')->default('metadata_only');
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_attachments');
    }
};
