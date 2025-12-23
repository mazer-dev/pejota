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
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            
            $table->string('name');
            $table->string('model')->nullable();
            $table->string('serial_no')->unique();
            
            // Maintenance & Status
            $table->date('last_maintenance_at')->nullable();
            $table->date('next_maintenance_at')->nullable();
            $table->string('status')->default('available'); // available, in_use, under_maintenance, broken

            // Tenancy & Location
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
