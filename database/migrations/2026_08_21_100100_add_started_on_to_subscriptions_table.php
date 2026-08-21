<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A data em que o compromisso começou. Entra nullable porque as linhas existentes
 * ainda não têm valor; a migration seguinte preenche e aperta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->date('started_on')->nullable()->after('billing_period');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('started_on');
        });
    }
};
