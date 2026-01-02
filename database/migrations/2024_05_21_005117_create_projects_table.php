<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{/**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id(); // id

            // Link to Branch (Replacing company_id)
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->string('code')->unique(); // code
            $table->string('name'); // name
            
            // Link to Client
            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();

            $table->date('start_date')->nullable(); // start_date
            $table->date('end_date')->nullable(); // end_date
            
            // budget (Using decimal for financial accuracy)
            $table->decimal('budget', 15, 2)->nullable(); 
            
            // status (e.g., 'active', 'completed', 'on-hold')
            $table->string('status')->default('active'); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
