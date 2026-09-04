<?php

use App\Services\NormalizeTaskListColumnSettings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Reescreve a chave de coluna renomeada em `users.settings ->
     * tasks.default_list_columns`. A coluna `client.labelName` virou `client`
     * em 3eda32b sem migrar o dado já gravado, e a chave órfã reprovava na
     * validação `in` das preferências. Idempotente.
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
