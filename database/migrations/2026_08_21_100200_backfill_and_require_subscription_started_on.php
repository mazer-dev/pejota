<?php

use App\Services\BackfillSubscriptionStartedOn;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preenche e aperta na MESMA migration, e as duas metades são inseparáveis: o
 * `NOT NULL` só é aplicável depois de não haver nulo, e o preenchimento só existe
 * para viabilizá-lo. Separá-las produziria um estado intermediário que não serve
 * a ninguém.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new BackfillSubscriptionStartedOn)();

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->date('started_on')->nullable(false)->change();
        });
    }

    /**
     * Devolve a coluna a nullable e NÃO desfaz o preenchimento, pelo mesmo motivo que
     * `BackfillWorkSessionCurrency` não é reversível: os valores gravados são um limite
     * superior verdadeiro do início real, e apagá-los destruiria informação sem devolver
     * nada.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->date('started_on')->nullable()->change();
        });
    }
};
