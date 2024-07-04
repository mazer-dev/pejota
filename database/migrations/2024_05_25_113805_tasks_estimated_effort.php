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
        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('effort')
                ->nullable()
                ->comment('related to effort unit');

            $table->string('effort_unit')
                ->default('h')
                ->comment('h = hour, m = minute');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
