<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->integer('default_hourly_rate')->nullable()->comment('in cents')->after('currency');
            $table->boolean('billable_default')->default(true)->after('default_hourly_rate');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->integer('hourly_rate')->nullable()->comment('in cents')->after('name');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('hourly_rate')->nullable()->comment('in cents')->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['default_hourly_rate', 'billable_default']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('hourly_rate');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('hourly_rate');
        });
    }
};
