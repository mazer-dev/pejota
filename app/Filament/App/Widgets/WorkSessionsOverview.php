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
                            Carbon::today()->startOfDay()->tz(PejotaHelper::getUserTimeZone()),
                            Carbon::today()->endOfDay()->tz(PejotaHelper::getUserTimeZone()),
                        ])
                        ->sum('duration')
                )
            ),

            Stat::make('This Week',
                PejotaHelper::formatDuration(
                    WorkSession::whereBetween('start',
                        [
                            Carbon::today()->startOfWeek()->tz(PejotaHelper::getUserTimeZone()),
                            Carbon::today()->endOfWeek()->tz(PejotaHelper::getUserTimeZone()),
                        ])
                        ->sum('duration')
                )
            ),

            Stat::make('This Month',
                PejotaHelper::formatDuration(
                    WorkSession::whereBetween('start',
                        [
                            Carbon::today()->startOfMonth()->tz(PejotaHelper::getUserTimeZone()),
                            Carbon::today()->endOfMonth()->tz(PejotaHelper::getUserTimeZone()),
                        ])
                        ->sum('duration')
                )
            ),

        ];
    }
}
