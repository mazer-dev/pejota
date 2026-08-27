<?php

namespace App\Services;

use App\Exceptions\MissingExchangeRateException;
use App\Models\ExchangeRate;
use Carbon\CarbonInterface;

class ExchangeRateService
{
    public const PIVOT = 'USD';

    /**
     * Latest stored rate (units of $currency per 1 USD) on or before $date, with carry-forward.
     * The pivot (USD) is the constant 1.0 and is never stored.
     *
     * @throws MissingExchangeRateException when no rate exists on or before $date
     */
    public function rateOn(string $currency, CarbonInterface $date): float
    {
        return $this->rateRowOn($currency, $date)['rate'];
    }

    /**
     * The rate and the DATE OF THE ROW it came from.
     *
     * `rateOn()` returns only the number, and the carry-forward is unbounded: the rate
     * can be months old without anything warning about it. Whoever displays the
     * converted number needs to be able to say how old it is (E4's R11).
     *
     * THE PIVOT HAS NO DATE. `USD` is 1.0 by definition, with no row in
     * `exchange_rates`; returning the requested date would invent provenance.
     *
     * @return array{rate: float, date: ?string}
     *
     * @throws MissingExchangeRateException when no rate exists on or before $date
     */
    public function rateRowOn(string $currency, CarbonInterface $date): array
    {
        if ($currency === self::PIVOT) {
            return ['rate' => 1.0, 'date' => null];
        }

        $row = ExchangeRate::query()
            ->where('currency_code', $currency)
            ->whereDate('date', '<=', $date)
            ->orderByDesc('date')
            ->first();

        if ($row === null) {
            throw new MissingExchangeRateException($currency, $date);
        }

        return ['rate' => (float) $row->rate, 'date' => $row->date->toDateString()];
    }

    /**
     * Convert $amount from currency $from to currency $to, triangulating via the USD pivot:
     * amount * rateOn($to) / rateOn($from). Same-currency conversions short-circuit (no DB).
     */
    public function convert(float $amount, string $from, string $to, CarbonInterface $date): float
    {
        if ($from === $to) {
            return $amount;
        }

        return $amount * $this->rateOn($to, $date) / $this->rateOn($from, $date);
    }
}
