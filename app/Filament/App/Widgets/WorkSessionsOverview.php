<?php

namespace App\Filament\App\Widgets;

use App\Helpers\PejotaHelper;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WorkSessionsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $today = CarbonImmutable::now(PejotaHelper::getUserTimeZone())->startOfDay();

        return [
            Stat::make(__('Today Sessions'),
                PejotaHelper::formatDuration(
                    WorkSession::whereBetween('start',
                        [
                            $today,
                            $today->endOfDay(),
                        ])
                        ->sum('duration')
                )
            )
            ->icon('heroicon-o-clock'),

            Stat::make(__('This Week'),
                PejotaHelper::formatDuration(
                    WorkSession::whereBetween('start',
                        [
                            $today->startOfWeek(),
                            $today->endOfWeek(),
                        ])
                        ->sum('duration')
                )
            )
            ->icon('heroicon-o-clock'),

            Stat::make(__('This Month'),
                PejotaHelper::formatDuration(
                    WorkSession::whereBetween('start',
                        [
                            $today->startOfMonth(),
                            $today->endOfMonth(),
                        ])
                        ->sum('duration')
                )
            )
            ->icon('heroicon-o-clock'),

        ];
    }
}
