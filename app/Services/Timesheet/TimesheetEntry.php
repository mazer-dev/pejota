<?php

namespace App\Services\Timesheet;

use Carbon\CarbonImmutable;

class TimesheetEntry
{
    public function __construct(
        public CarbonImmutable $date,
        public ?string $taskTitle,
        public ?string $projectName,
        public ?string $description,
        public int $minutes,
        public ?float $rate,
        public float $value,
    ) {}
}
