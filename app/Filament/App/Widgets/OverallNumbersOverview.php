<?php

namespace App\Filament\App\Widgets;

use App\Enums\StatusPhaseEnum;
use App\Models\Client;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Project;
class ProjectsNumberOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Clients', Client::count()),
            Stat::make('Projects', Project::count()),
            Stat::make('Tasks Opened',
                Task::whereHas('status', function ($query) {
                    return $query->whereIn('phase', [
                        StatusPhaseEnum::TODO,
                        StatusPhaseEnum::IN_PROGRESS
                    ]);
                })->count()
            ),
        ];
    }
}
