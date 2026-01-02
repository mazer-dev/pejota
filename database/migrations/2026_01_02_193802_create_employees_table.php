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
    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        
        // Basic Information
        $table->string('full_name'); // The employee's legal full name
        $table->string('email')->unique(); // Unique work or personal email address
        $table->string('phone'); // Contact phone number
        
        // Job Details
        $table->string('job_title'); // Designation or role (e.g., Site Engineer, Accountant)
        $table->decimal('salary', 10, 2); // Monthly or annual base salary
        $table->date('hire_date'); // The official date the employee joined the company
        
        // Employment Status
        $table->enum('status', ['active', 'inactive', 'on_leave'])->default('active'); // Current work status
        
        $table->timestamps(); // Created_at and updated_at timestamps
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
