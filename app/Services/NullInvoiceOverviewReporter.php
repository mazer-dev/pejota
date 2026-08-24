<?php

namespace App\Services;

use App\Contracts\InvoiceOverviewReporter;
use App\Models\Company;
use App\Models\Invoice;
use Carbon\CarbonImmutable;

/**
 * O comportamento histórico do `InvoicesOverview`, movido para trás do contrato sem
 * uma linha de lógica nova. Se algum número mudar em relação ao que o widget devolvia
 * antes deste arquivo existir, é defeito — não melhoria.
 *
 * `$company` É DELIBERADAMENTE IGNORADO. Os scopes usados aqui já são restritos ao
 * tenant pelo escopo global do samehouse, exatamente como o widget dependia antes. O
 * parâmetro existe no contrato porque uma implementação que rode SEM tenant de painel
 * precisa dele — e passá-lo aqui, só para "usar", trocaria o mecanismo de tenancy no
 * meio de um refactor que promete não mudar número nenhum.
 *
 * `$timezone` também é ignorado: os scopes resolvem "hoje" por dentro
 * (`Invoice::currentDay()`), e substituir isso por `$today` mudaria a fronteira de
 * `overdue` em até um dia. Mesma razão. `$baseCurrency` também é ignorado pelo mesmo
 * motivo: `Invoice::baseTotal`, usado por `InvoiceService::sumBaseCurrency()`, resolve
 * a moeda base sozinho via `PejotaHelper::getUserCurrency()`, independente de qualquer
 * parâmetro.
 */
class NullInvoiceOverviewReporter implements InvoiceOverviewReporter
{
    public function __construct(private InvoiceService $invoices) {}

    /**
     * @return array{
     *   pending:  array{total: float, unconverted: int},
     *   overdue:  array{total: float, unconverted: int},
     *   due_soon: array{total: float, unconverted: int},
     *   received: array{total: float, unconverted: int},
     * }
     */
    public function summary(
        Company $company,
        string $baseCurrency,
        string $timezone,
        CarbonImmutable $today,
        int $dueWithinDays,
        int $receivedDays,
    ): array {
        return [
            'pending' => $this->invoices->sumBaseCurrency(Invoice::pending()->get()),
            'overdue' => $this->invoices->sumBaseCurrency(Invoice::overdue()->get()),
            'due_soon' => $this->invoices->sumBaseCurrency(Invoice::dueWithin($dueWithinDays)->get()),
            'received' => $this->invoices->sumBaseCurrency(
                Invoice::receivedBetween($today->subDays($receivedDays), $today)->get(),
            ),
        ];
    }
}
