<?php

namespace App\Filament\App\Widgets;

use App\Helpers\PejotaHelper;
use App\Models\WorkSession;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WorkSessionsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Today Sessions',
                PejotaHelper::formatDuration(
                    WorkSession::whereBetween('start',
                        [
                            Carbon::today()->tz(PejotaHelper::getUserTimeZone())->startOfDay(),
                            Carbon::today()->tz(PejotaHelper::getUserTimeZone())->endOfDay(),
                        ])
                        ->sum('duration')
                )
            ),

            Stat::make('This Week',
                PejotaHelper::formatDuration(
                    WorkSession::whereBetween('start',
                        [
                            Carbon::today()->tz(PejotaHelper::getUserTimeZone())->startOfWeek(),
                            Carbon::today()->tz(PejotaHelper::getUserTimeZone())->endOfWeek(),
                        ])
                        ->sum('duration')
                )
            ),

            Stat::make('This Month',
                PejotaHelper::formatDuration(
                    WorkSession::whereBetween('start',
                        [
                            Carbon::today()->tz(PejotaHelper::getUserTimeZone())->startOfMonth(),
                            Carbon::today()->tz(PejotaHelper::getUserTimeZone())->endOfMonth(),
                        ])
                        ->sum('duration')
                )
            ),

        ];
    }
}
