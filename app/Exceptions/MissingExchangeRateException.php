<?php

namespace App\Exceptions;

use Carbon\CarbonInterface;
use RuntimeException;

class MissingExchangeRateException extends RuntimeException
{
    public function __construct(string $currency, CarbonInterface $date)
    {
        parent::__construct("No exchange rate for {$currency} on or before {$date->toDateString()}.");
    }
}
