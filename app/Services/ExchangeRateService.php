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
        if ($currency === self::PIVOT) {
            return 1.0;
        }

        $row = ExchangeRate::query()
            ->where('currency_code', $currency)
            ->whereDate('date', '<=', $date)
            ->orderByDesc('date')
            ->first();

        if ($row === null) {
            throw new MissingExchangeRateException($currency, $date);
        }

        return (float) $row->rate;
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
