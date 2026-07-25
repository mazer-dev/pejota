<?php

namespace App\Services\Planner;

final class ParsedDailyPlan
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly string $summary,
        public readonly array $items,
        public readonly array $warnings,
    ) {}

    public function plannedMinutes(): int
    {
        return (int) array_sum(array_column($this->items, 'estimated_minutes'));
    }
}
