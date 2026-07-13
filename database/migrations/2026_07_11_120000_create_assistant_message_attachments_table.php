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
        Schema::create('assistant_message_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('assistant_message_id')
                ->constrained('assistant_messages')
                ->cascadeOnDelete();

            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('extension')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('sha256')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('status')->default('stored');
            $table->longText('extracted_text')->nullable();
            $table->longText('summary')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['assistant_message_id', 'id']);
            $table->index(['company_id', 'status']);
            $table->index('sha256');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_message_attachments');
    }
};
