<?php

use App\Services\BackfillWorkSessionCurrency;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Corrige a moeda das sessões que caíram no default `'USD'` do schema sem
     * ninguém ter escolhido isso. Só toca em sessão com `value = 0` — ver o
     * docblock do serviço para o porquê. Idempotente.
     */
    public function up(): void
    {
        (new BackfillWorkSessionCurrency)();
    }

    public function down(): void
    {
        // Não reversível, e de propósito: o valor anterior era o default do
        // schema, não uma escolha. Restaurá-lo reintroduziria o defeito.
    }
};
