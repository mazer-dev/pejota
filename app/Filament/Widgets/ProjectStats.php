<?php

namespace App\Filament\Widgets;
use App\Models\Project;
use App\Models\DailyLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            /* Statistic for the total number of projects */
            Stat::make('Total Projects', Project::count())
                ->description('All registered projects in the system')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('info'),

            /* Statistic for logs created today */
            Stat::make('Daily Logs Today', DailyLog::whereDate('log_date', today())->count())
                ->description('Total logs submitted today')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('success'),
        ];
    }
}
