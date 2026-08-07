<?php

namespace App\Services;

use App\Enums\CompanySettingsEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BackfillWorkSessionCurrency
{
    /**
     * Corrige a moeda das sessões que nunca tiveram moeda escolhida.
     *
     * O PROBLEMA: `work_sessions.currency` tem default `'USD'` no schema
     * (`2024_05_21_012039_create_work_sessions_table.php:27`). Esse valor não é
     * decisão de ninguém — é o que o banco carimba quando o caminho de criação
     * não resolve a moeda. E até 2026-08-07 a resolução vivia só em três pontos
     * de UI, nunca no model, então qualquer outro caminho de criação gravava
     * `'USD'` numa empresa que pode ser de qualquer moeda.
     *
     * SÓ TOCA EM SESSÃO COM `value = 0`, e essa guarda é o coração do serviço.
     * Não há como distinguir, olhando o dado, "caiu no default USD" de "o usuário
     * escolheu USD porque o trabalho é em dólar". Mas uma sessão que vale zero não
     * carrega consequência financeira: recarimbar a moeda dela não muda número
     * nenhum. Sessão COM valor é dinheiro, e dinheiro não se reinterpreta em
     * silêncio — ela é contada e devolvida no relatório para decisão humana.
     *
     * Não-destrutivo e idempotente: rodar de novo não muda nada.
     *
     * @return array{updated: int, skipped_with_value: int}
     */
    public function __invoke(): array
    {
        $updated = 0;
        $skipped = 0;

        DB::table('companies')->orderBy('id')->each(function (object $company) use (&$updated, &$skipped): void {
            $settings = json_decode($company->settings ?? '[]', true);
            $companyCurrency = is_array($settings)
                ? Arr::get($settings, CompanySettingsEnum::FINANCE_CURRENCY->value)
                : null;

            DB::table('work_sessions')
                ->where('company_id', $company->id)
                ->orderBy('id')
                ->each(function (object $session) use ($companyCurrency, &$updated, &$skipped): void {
                    $clientCurrency = $session->client_id === null
                        ? null
                        : DB::table('clients')->where('id', $session->client_id)->value('currency');

                    $resolved = $clientCurrency ?: $companyCurrency;

                    if ($resolved === null || $resolved === $session->currency) {
                        return;
                    }

                    if ((int) $session->value !== 0) {
                        $skipped++;

                        return;
                    }

                    DB::table('work_sessions')
                        ->where('id', $session->id)
                        ->update(['currency' => $resolved]);

                    $updated++;
                });
        });

        return ['updated' => $updated, 'skipped_with_value' => $skipped];
    }
}
