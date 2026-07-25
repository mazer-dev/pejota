<?php

namespace App\Services\Planner;

use App\Enums\CompanySettingsEnum;
use App\Models\Company;
use App\Models\WorkSession;
use Carbon\CarbonInterface;

/**
 * Working capacity configured per ISO weekday (1 = Monday .. 7 = Sunday)
 * in the company settings. A day with 0 hours is a day off: the planner
 * still runs, but in "light" mode (urgent items only).
 */
final class PlannerCapacity
{
    /**
     * @param  array<int, float>  $dayHours
     */
    private function __construct(
        private readonly Company $company,
        private readonly array $dayHours,
    ) {}

    public static function forCompany(Company $company): self
    {
        $defaults = CompanySettingsEnum::getDefaultPlannerDayHours();
        $stored = $company->settings()->get(CompanySettingsEnum::PLANNER_DAY_HOURS->value);

        $dayHours = [];
        foreach ($defaults as $isoWeekday => $defaultHours) {
            $value = is_array($stored) ? ($stored[$isoWeekday] ?? $stored[(string) $isoWeekday] ?? null) : null;
            $dayHours[$isoWeekday] = is_numeric($value) ? max(0.0, (float) $value) : (float) $defaultHours;
        }

        return new self($company, $dayHours);
    }

    public function isWorkDay(CarbonInterface $date): bool
    {
        return $this->dayHours($date) > 0;
    }

    public function dayHours(CarbonInterface $date): float
    {
        return $this->dayHours[$date->isoWeekday()] ?? 0.0;
    }

    public function dayCapacityMinutes(CarbonInterface $date): int
    {
        return (int) round($this->dayHours($date) * 60);
    }

    public function weeklyMinutes(): int
    {
        return (int) round(array_sum($this->dayHours) * 60);
    }

    /**
     * Minutes logged in work sessions on the reference day (user timezone).
     */
    public function workedOnDayMinutes(CarbonInterface $reference): int
    {
        return $this->workedBetweenMinutes(
            $reference->copy()->startOfDay(),
            $reference->copy()->endOfDay(),
        );
    }

    public function workedThisWeekMinutes(CarbonInterface $reference): int
    {
        return $this->workedBetweenMinutes(
            $reference->copy()->startOfWeek(),
            $reference->copy()->endOfWeek(),
        );
    }

    public function remainingTodayMinutes(CarbonInterface $reference): int
    {
        return max(0, $this->dayCapacityMinutes($reference) - $this->workedOnDayMinutes($reference));
    }

    private function workedBetweenMinutes(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) WorkSession::allTenants()
            ->where('company_id', $this->company->id)
            ->whereBetween('start', [$from->copy()->utc(), $to->copy()->utc()])
            ->sum('duration');
    }
}
