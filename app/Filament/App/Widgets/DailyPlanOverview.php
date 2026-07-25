<?php

namespace App\Filament\App\Widgets;

use App\Enums\DailyPlanItemStatusEnum;
use App\Filament\App\Pages\PlanOfTheDay;
use App\Helpers\PejotaHelper;
use App\Models\DailyPlan;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

class DailyPlanOverview extends Widget
{
    protected static ?int $sort = 100;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 3];

    protected static string $view = 'filament.app.widgets.daily-plan-overview';

    public static function canView(): bool
    {
        return static::todayPlan() !== null;
    }

    protected static function todayPlan(): ?DailyPlan
    {
        return DailyPlan::query()
            ->where('company_id', auth()->user()->company->id)
            ->forDate(CarbonImmutable::now(PejotaHelper::getUserTimeZoneOrDefault())->startOfDay())
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $plan = static::todayPlan()?->load('items');

        $items = $plan?->items ?? collect();
        $pending = $items->where('status', DailyPlanItemStatusEnum::PENDING)->take(3);

        return [
            'plan' => $plan,
            'nextItems' => $pending,
            'doneCount' => $items->where('status', DailyPlanItemStatusEnum::DONE)->count(),
            'totalCount' => $items->count(),
            'pageUrl' => PlanOfTheDay::getUrl(),
        ];
    }
}
