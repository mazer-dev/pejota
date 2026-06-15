<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_recurrence_template')->default(false)->after('parent_id');
            $table->boolean('is_continuous')->default(false)->after('is_recurrence_template');
            $table->string('continuous_mode')->nullable()->after('is_continuous');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['is_recurrence_template', 'is_continuous', 'continuous_mode']);
        });
    }
};
