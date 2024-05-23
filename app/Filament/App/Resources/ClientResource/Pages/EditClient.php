<?php

namespace App\Filament\App\Resources\ClientResource\Pages;

use App\Filament\App\Resources\ClientResource;
use Filament\Actions;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\EditRecord;
use Parallax\FilamentComments\Actions\CommentsAction;
use Parallax\FilamentComments\Infolists\Components\CommentsEntry;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommentsAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

        public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                CommentsEntry::make('filament_comments'),
            ]);
    }
}
