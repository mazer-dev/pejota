<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('filament-comments.table_name', 'filament_comments'), function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table(config('filament-comments.table_name', 'filament_comments'), function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
        });
    }
};
