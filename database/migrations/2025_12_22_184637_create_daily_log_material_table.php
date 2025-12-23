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
       Schema::create('daily_log_material', function (Blueprint $table) {
        $table->id();
        $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete(); // Reference to the daily log
        $table->foreignId('material_id')->constrained()->cascadeOnDelete(); // Reference to the specific material
        $table->decimal('quantity', 10, 2); // Amount used during the day
        $table->timestamps();
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_log_material');
    }
};
