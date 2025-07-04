<?php

namespace App\Filament\App\Widgets;

use App\Enums\StatusPhaseEnum;
use App\Filament\App\Resources\ClientResource;
use App\Filament\App\Resources\ProjectResource;
use App\Filament\App\Resources\TaskResource;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Filament\Support\Colors\Color;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TasksOverview extends BaseWidget
{
    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('Tasks due today'),
                Task::whereHas('status', function ($query) {
                    return $query->whereIn('phase', [
                        StatusPhaseEnum::TODO,
                        StatusPhaseEnum::IN_PROGRESS,
                    ])
                        ->whereDate('due_date', now(PejotaHelper::getUserTimeZone()));
                })->count()
            )
                ->icon('heroicon-o-exclamation-circle')
                ->color('warning'),

            Stat::make(__('Tasks late due dates'),
                Task::whereHas('status', function ($query) {
                    return $query->whereIn('phase', [
                        StatusPhaseEnum::TODO,
                        StatusPhaseEnum::IN_PROGRESS,
                    ])
                        ->whereDate('due_date', '<', now(PejotaHelper::getUserTimeZone()));
                })->count()
            )
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),

            Stat::make(__('Tasks planned for today'),
                Task::whereHas('status', function ($query) {
                    return $query->whereIn('phase', [
                        StatusPhaseEnum::TODO,
                        StatusPhaseEnum::IN_PROGRESS,
                    ])
                        ->whereDate('planned_end', now(PejotaHelper::getUserTimeZone()));
                })->count()
            )
                ->icon('heroicon-o-check-circle'),

            Stat::make(__('Tasks planned late'),
                Task::whereHas('status', function ($query) {
                    return $query->whereIn('phase', [
                        StatusPhaseEnum::TODO,
                        StatusPhaseEnum::IN_PROGRESS,
                    ])
                        ->whereDate('planned_end', '<', now(PejotaHelper::getUserTimeZone()));
                })->count()
            )
                ->icon('heroicon-o-shield-check'),
        ];
    }
}
