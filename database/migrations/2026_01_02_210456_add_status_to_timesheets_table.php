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
    Schema::table('timesheets', function (Blueprint $table) {
        // Adding the status column with a default value of 'pending'
        $table->string('status')->default('pending')->after('hours'); 
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            //
        });
    }
};
