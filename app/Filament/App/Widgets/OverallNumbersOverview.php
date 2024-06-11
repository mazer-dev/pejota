<?php

namespace App\Filament\App\Widgets;

use App\Enums\StatusPhaseEnum;
use App\Filament\App\Resources\ClientResource;
use App\Filament\App\Resources\ProjectResource;
use App\Filament\App\Resources\TaskResource;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverallNumbersOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Clients', Client::count())
                ->icon(ClientResource::getNavigationIcon()),

            Stat::make('Projects', Project::count())
                ->icon(ProjectResource::getNavigationIcon()),

            Stat::make('Tasks Opened',
                Task::whereHas('status', function ($query) {
                    return $query->whereIn('phase', [
                        StatusPhaseEnum::TODO,
                        StatusPhaseEnum::IN_PROGRESS,
                    ]);
                })->count()
            )
                ->icon(TaskResource::getNavigationIcon()),
        ];
    }
}
