<?php

namespace App\Filament\App\Resources\NoteResource\Pages;

use App\Filament\App\Resources\NoteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewNote extends ViewRecord
{
    protected static string $resource = NoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
