<?php

namespace App\Filament\App\Resources\TaskResource\Pages;

use App\Filament\App\Resources\TaskResource;
use App\Models\Task;
use Filament\Actions;
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
            Actions\CreateAction::make(),
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
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->opened())
                ->badge(fn (Task $record): int => $record->opened()->count())
                ->badgeColor(Color::Orange),
            'closed' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->closed()),
            'all' => Tab::make(),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return session('tasks_active_tab_'.auth()->user()->id, 'all');
    }
}
