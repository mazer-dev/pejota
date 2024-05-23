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
        Schema::create('task_histories', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('task_id');
            $table->foreign('task_id')
                ->references('id')
                ->on('tasks')
                ->restrictOnDelete();

            $table->unsignedInteger('status_id');
            $table->foreign('status_id')
                ->references('id')
                ->on('statuses')
                ->restrictOnDelete();

            $table->unsignedInteger('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->text('comment');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_histories');
    }
};
