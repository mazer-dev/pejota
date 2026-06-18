<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->integer('value')->default(0)->comment('in cents')->after('rate');
            $table->boolean('billable')->default(true)->after('value');
            $table->foreignId('invoice_item_id')
                ->nullable()
                ->after('billable')
                ->constrained('invoice_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_item_id');
            $table->dropColumn(['value', 'billable']);
        });
    }
};
