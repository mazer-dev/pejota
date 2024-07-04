<?php

namespace App\Filament\App\Resources\NoteResource\Pages;

use App\Filament\App\Resources\NoteResource;
use Filament\Actions;
use Filament\Infolists\Components\SpatieTagsEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewNote extends ViewRecord
{
    protected static string $resource = NoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist;

        return $infolist->schema([
            TextEntry::make('title'),
            SpatieTagsEntry::make('tags'),
            TextEntry::make('client.name'),
            TextEntry::make('project.name'),
        ]);
    }
}
