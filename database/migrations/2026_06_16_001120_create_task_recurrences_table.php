<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_recurrences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('task_id');
            $table->string('frequency');
            $table->unsignedInteger('interval')->default(1);
            $table->string('anchor_field')->default('due_date');
            $table->integer('offset_days')->default(0);
            $table->string('generation_mode')->default('by_date');
            $table->string('stop_type')->default('never');
            $table->date('until_date')->nullable();
            $table->unsignedInteger('max_count')->nullable();
            $table->unsignedInteger('generated_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('next_run_date')->nullable();
            $table->date('last_generated_date')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_recurrences');
    }
};
