<?php

namespace App\Filament\App\Resources\TaskResource\Pages;

use App\Filament\App\Resources\TaskResource;
use App\Models\Task;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function render(): View
    {
        $result = parent::render();

        session()->put('tasks_active_tab_'.auth()->user()->id, $this->activeTab);

        return $result;
    }

    public function getTabs(): array
    {
        return [
            'opened' => Tab::make()
                ->label(__('Opened'))
                ->modifyQueryUsing(function (Builder $query): Builder {
                    $query->opened()->excludeDoneTodayChecks();

                    if ($this->getTableSortColumn() === null) {
                        $query->orderedForList();
                    }

                    return $query;
                })
                ->badge(fn (Task $record): int => $record->opened()->excludeDoneTodayChecks()->count())
                ->badgeColor(Color::Orange),
            'daily_checks' => Tab::make()
                ->label(__('Daily checks'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_continuous', true))
                ->badge(fn (Task $record): int => $record->where('is_continuous', true)->count())
                ->badgeColor(Color::Amber),
            'closed' => Tab::make()
                ->label(__('Closed'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->closed()),
            'all' => Tab::make()
                ->label(__('All')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return session('tasks_active_tab_'.auth()->user()->id, 'opened');
    }
}
