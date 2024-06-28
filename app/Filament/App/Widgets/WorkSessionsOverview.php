<?php

namespace App\Filament\App\Widgets;

use App\Helpers\PejotaHelper;
use App\Models\WorkSession;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WorkSessionsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $today = CarbonImmutable::today()->tz(PejotaHelper::getUserTimeZone())->startOfDay();

        return [
            Stat::make('Today Sessions',
                PejotaHelper::formatDuration(
                    WorkSession::whereBetween('start',
                        [
                            $today,
                            $today->endOfDay(),
                        ])
                        ->sum('duration')
                )
            ),

            Stat::make('This Week',
                PejotaHelper::formatDuration(
                    WorkSession::whereBetween('start',
                        [
                            $today->startOfWeek(),
                            $today->endOfWeek(),
                        ])
                        ->sum('duration')
                )
            ),

            Stat::make('This Month',
                PejotaHelper::formatDuration(
                    WorkSession::whereBetween('start',
                        [
                            $today->startOfMonth(),
                            $today->endOfMonth(),
                        ])
                        ->sum('duration')
                )
            ),

        ];
    }
}
