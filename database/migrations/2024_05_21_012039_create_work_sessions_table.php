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
        Schema::create('work_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();

            $table->boolean('is_running')->default(true);

            $table->text('description')->nullable();
            $table->unsignedInteger('duration')
                ->nullable()->comment('in minutes');
            $table->timestamp('start');
            $table->timestamp('end')->nullable();
            $table->integer('rate')
                ->default(0)->comment('in cents');
            $table->string('currency', 3)->default('USD');

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->restrictOnDelete();

            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects')
                ->restrictOnDelete();

            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tasks')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_sessions');
    }
};
