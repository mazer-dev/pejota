<?php

namespace App\Contracts;

use App\Models\Company;
use Carbon\CarbonImmutable;

/**
 * Os quatro números do painel de faturas, atrás de um contrato.
 *
 * POR QUE ELE EXISTE: neste produto (aberto) uma fatura tem UM vencimento e é paga
 * inteira, então somar `invoices.total` é a resposta certa — e é tudo o que há. Numa
 * distribuição que acrescente cronograma de parcelas e um razão de recebimentos, a
 * mesma soma passa a mentir, e o número certo não é computável a partir das tabelas
 * daqui. Este contrato é o ponto onde aquela distribuição religa.
 *
 * TODO PARÂMETRO É OBRIGATÓRIO, e nenhum tem default. Uma implementação pode precisar
 * rodar fora de request de painel — num command agendado, por exemplo —, onde
 * `PejotaHelper::getUserCurrency()` devolve `'USD'` e `getUserTimeZone()` devolve
 * `null`. Resolver ambiente é do CHAMADOR.
 *
 * A UNIDADE É UNIDADE MONETÁRIA (float), não centavos: é o que
 * `InvoiceService::sumBaseCurrency()` já devolve e o que o widget formata. Uma
 * implementação que some em centavos converte na saída, não aqui.
 *
 * `unconverted` é CONTAGEM do que ficou de fora de `total` por falta de cotação —
 * nunca somado como zero, nunca estimado. A unidade da contagem é a da implementação
 * (aqui, faturas).
 */
interface InvoiceOverviewReporter
{
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
    ): array;
}
