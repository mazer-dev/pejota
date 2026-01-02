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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            // What is moving?
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            
            // Where is it moving?
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            
            // Tenancy
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Movement Details
            $table->decimal('qty', 12, 2);
            $table->string('type'); // in, out, transfer, adjustment
            
            // Reference to the source (e.g., Purchase Order ID or Task ID)
            $table->unsignedBigInteger('reference_id')->nullable(); 
            $table->string('reference_type')->nullable(); // To know if it's a PO or a Project usage

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
