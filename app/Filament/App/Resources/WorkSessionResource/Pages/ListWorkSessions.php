<?php

namespace App\Filament\App\Resources\WorkSessionResource\Pages;

use App\Filament\App\Resources\WorkSessionResource;
use App\Models\Task;
use App\Models\WorkSession;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;

class ListWorkSessions extends ListRecords
{
    protected static string $resource = WorkSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'running' => Tab::make()
                ->label(__('Running'))
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('is_running', true))
                ->badge(fn(WorkSession $record): int => $record->where('is_running', true)->count())
                ->badgeColor(Color::Green),
            'all' => Tab::make()
                ->label(__('All')),
        ];
    }

}
