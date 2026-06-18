<?php

namespace App\Services\Timesheet;

use App\Enums\TimesheetDetailLevel;
use App\Enums\TimesheetGrouping;
use Carbon\CarbonImmutable;

class TimesheetRequest
{
    public function __construct(
        public int $clientId,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $timezone,
        public string $currency,
        public TimesheetGrouping $grouping,
        public TimesheetDetailLevel $detailLevel,
        public bool $includeValue,
        public bool $billableOnly,
        public string $layoutKey,
    ) {}
}
