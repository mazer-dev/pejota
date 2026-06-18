<?php

namespace App\Services\Timesheet;

use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TimesheetData
{
    /**
     * @param  Collection<int, TimesheetGroup>  $groups
     */
    public function __construct(
        public Client $client,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $currency,
        public string $layoutKey,
        public bool $includeValue,
        public Collection $groups,
        public int $grandTotalMinutes,
        public float $grandTotalValue,
    ) {}
}
