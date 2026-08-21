<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BackfillSubscriptionStartedOn
{
    /**
     * Preenche `subscriptions.started_on` a partir da data de criação da linha.
     *
     * `created_at` é LIMITE SUPERIOR do início real — assina-se primeiro e cadastra-se
     * depois —, então "existe pelo menos desde esta data" é afirmação verdadeira, e não
     * uma data inventada. Onde a data exata é conhecida, o usuário edita.
     *
     * `eachById()` e não `each()`: o filtro é a própria coluna que o laço escreve, e cada
     * linha preenchida SAI do conjunto filtrado. Uma paginação por offset — que é o que
     * `each()` faz — pularia linhas a cada página, porque a janela desliza embaixo dela.
     * `eachById()` pagina por `id > último`, e é imune a isso.
     *
     * Grava pelo query builder, não pelo model: não há tenant num processo de migration,
     * e um `UPDATE` cru não mexe em `updated_at` — o backfill corrige uma ausência, não
     * registra uma edição do usuário.
     *
     * Não-destrutivo e idempotente: rodar de novo não muda nada.
     *
     * @return array{updated: int}
     */
    public function __invoke(): array
    {
        $updated = 0;

        DB::table('subscriptions')
            ->whereNull('started_on')
            ->eachById(function (object $subscription) use (&$updated): void {
                DB::table('subscriptions')
                    ->where('id', $subscription->id)
                    ->update([
                        'started_on' => CarbonImmutable::parse($subscription->created_at)->toDateString(),
                    ]);

                $updated++;
            });

        return ['updated' => $updated];
    }
}
