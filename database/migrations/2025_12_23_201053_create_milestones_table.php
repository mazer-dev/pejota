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
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
           /* Link each milestone to a specific project */
        $table->foreignId('project_id')->constrained()->onDelete('cascade');
        
        /* The name of the milestone (e.g., Excavation, Foundation) */
        $table->string('name');
        
        /* Expected completion date */
        $table->date('due_date')->nullable();
        
        /* Track if the milestone is achieved */
        $table->boolean('is_completed')->default(false);
        $table->timestamps();
    
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
