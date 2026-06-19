<?php

namespace App\Services\Invoicing;

use App\Enums\TimesheetGrouping;
use Carbon\CarbonImmutable;

class SessionInvoiceRequest
{
    public function __construct(
        public int $clientId,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $timezone,
        public TimesheetGrouping $grouping,
        public int $productId,
        public int $unitId,
    ) {}
}
