<?php

namespace App\Services\Timesheet;

use Illuminate\Support\Collection;

class TimesheetGroup
{
    /**
     * @param  Collection<int, TimesheetEntry>  $entries
     */
    public function __construct(
        public string $label,
        public Collection $entries,
        public int $subtotalMinutes,
        public float $subtotalValue,
    ) {}
}
