<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('ai_context')->nullable()->after('billable_default');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->text('ai_context')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('ai_context');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('ai_context');
        });
    }
};
