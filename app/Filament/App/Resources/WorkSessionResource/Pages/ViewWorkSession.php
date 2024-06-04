<?php

namespace App\Filament\App\Resources\WorkSessionResource\Pages;

use App\Filament\App\Resources\WorkSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkSession extends ViewRecord
{
    protected static string $resource = WorkSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
