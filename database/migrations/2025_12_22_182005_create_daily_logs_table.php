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
        Schema::create('daily_logs', function (Blueprint $table) {
           $table->id();
           $table->foreignId('project_id')->constrained()->cascadeOnDelete(); // Link report to a specific project
           $table->date('log_date'); // Date of the log
           $table->text('description'); // Daily site activities
           $table->string('weather')->nullable(); // Weather condition (optional)
           $table->json('photos')->nullable(); // For uploading site photos
           $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_logs');
    }
};
