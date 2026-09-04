<?php

use App\Services\NormalizeTaskListColumnSettings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Ajusta `users.settings -> tasks.default_list_columns` a duas mudanças de
     * coluna que não migraram o dado já gravado: `client.labelName` virou
     * `client` em 3eda32b, e a chave órfã reprovava na validação `in` das
     * preferências; `done_today` passou a respeitar a preferência e é
     * acrescentada para manter visível a coluna que antes era imposta.
     * Idempotente.
     */
    public function up(): void
    {
        (new NormalizeTaskListColumnSettings)();
    }

    public function down(): void
    {
        // Não reversível: a chave antiga não corresponde a nenhuma coluna
        // existente, então restaurá-la reintroduziria o defeito.
    }
};
