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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('normal');

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('tasks')
                ->restrictOnDelete();

            $table->foreignId('status_id')
                ->constrained('statuses')
                ->restrictOnDelete();

            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->date('due_date')->nullable();

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

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
