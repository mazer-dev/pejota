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

            $table->unsignedInteger('parent_id')->nullable();
            $table->foreign('parent_id')
                ->references('id')
                ->on('tasks')
                ->restrictOnDelete();

            $table->unsignedInteger('status_id');
            $table->foreign('status_id')
                ->references('id')
                ->on('statuses')
                ->restrictOnDelete();

            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->date('due_date')->nullable();

            $table->unsignedInteger('company_id');
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->restrictOnDelete();

            $table->unsignedInteger('client_id')->nullable();
            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->restrictOnDelete();

            $table->unsignedInteger('project_id')->nullable();
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
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
