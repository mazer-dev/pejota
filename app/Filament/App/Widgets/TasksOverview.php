<?php

namespace App\Filament\App\Widgets;

use App\Enums\StatusPhaseEnum;
use App\Filament\App\Resources\TaskResource\Pages\ListTasks;
use App\Helpers\PejotaHelper;
use App\Models\Task;
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
                ->color('warning')
                ->url(ListTasks::getUrl([
                    'tableFilters[due_date][from_due_date]' => now(PejotaHelper::getUserTimeZone())->format('Y-m-d'),
                    'tableFilters[due_date][to_due_date]' => now(PejotaHelper::getUserTimeZone())->format('Y-m-d'),
                ])),

            Stat::make(__('Tasks overdue'),
                Task::whereHas('status', function ($query) {
                    return $query->whereIn('phase', [
                        StatusPhaseEnum::TODO,
                        StatusPhaseEnum::IN_PROGRESS,
                    ])
                        ->whereDate('due_date', '<', now(PejotaHelper::getUserTimeZone()));
                })->count()
            )
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->url(ListTasks::getUrl([
                    'tableFilters[due_date][to_due_date]' => now(PejotaHelper::getUserTimeZone())->subDay()->format('Y-m-d'),
                ])),

            Stat::make(__('Tasks planned for today'),
                Task::whereHas('status', function ($query) {
                    return $query->whereIn('phase', [
                        StatusPhaseEnum::TODO,
                        StatusPhaseEnum::IN_PROGRESS,
                    ])
                        ->whereDate('planned_end', now(PejotaHelper::getUserTimeZone()));
                })->count()
            )
                ->icon('heroicon-o-check-circle')
                ->url(ListTasks::getUrl([
                    'tableFilters[planned_end][from_planned_end]' => now(PejotaHelper::getUserTimeZone())->format('Y-m-d'),
                    'tableFilters[planned_end][to_planned_end]' => now(PejotaHelper::getUserTimeZone())->format('Y-m-d'),
                ])),

            Stat::make(__('Tasks planned late'),
                Task::whereHas('status', function ($query) {
                    return $query->whereIn('phase', [
                        StatusPhaseEnum::TODO,
                        StatusPhaseEnum::IN_PROGRESS,
                    ])
                        ->whereDate('planned_end', '<', now(PejotaHelper::getUserTimeZone()));
                })->count()
            )
                ->icon('heroicon-o-shield-check')
                ->url(ListTasks::getUrl([
                    'tableFilters[planned_end][to_planned_end]' => now(PejotaHelper::getUserTimeZone())->subDay()->format('Y-m-d'),
                ])),
        ];
    }
}
