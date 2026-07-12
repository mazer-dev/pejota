<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'settings')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('settings')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'settings')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('settings');
            });
        }
    }
};
