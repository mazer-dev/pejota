<?php

namespace App\Filament\App\Resources\TaskResource\Pages;

use App\Enums\StatusPhaseEnum;
use App\Filament\App\Resources\TaskResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
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
            'all' => Tab::make(),
            'opened' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas('status', function (Builder $query) {
                    $query->whereIn('phase', [
                        StatusPhaseEnum::TODO,
                        StatusPhaseEnum::IN_PROGRESS,
                    ]);
                })),
            'closed' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas('status', function (Builder $query) {
                    $query->whereIn('phase', [
                        StatusPhaseEnum::CLOSED,
                    ]);
                })),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return session('tasks_active_tab_'.auth()->user()->id, 'all');
    }
}
