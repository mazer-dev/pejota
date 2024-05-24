<?php

namespace App\Filament\App\Resources\TaskResource\Pages;

use App\Enums\PhaseEnum;
use App\Enums\StatusPhaseEnum;
use App\Filament\App\Resources\TaskResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
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

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(),
            'opened' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query): Builder
                    => $query->whereHas('status', function (Builder $query) {
                        $query->whereIn('phase', [
                            StatusPhaseEnum::TODO,
                            StatusPhaseEnum::IN_PROGRESS,
                        ]);
                    })),
            'closed' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query): Builder
                    => $query->whereHas('status', function (Builder $query) {
                        $query->whereIn('phase', [
                            StatusPhaseEnum::CLOSED,
                        ]);
                    })),
        ];
    }
}
