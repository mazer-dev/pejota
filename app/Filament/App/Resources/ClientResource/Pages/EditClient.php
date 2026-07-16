<?php

namespace App\Filament\App\Resources\ClientResource\Pages;

use App\Filament\App\Resources\ClientResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Parallax\FilamentComments\Actions\CommentsAction;
use Parallax\FilamentComments\Infolists\Components\CommentsEntry;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommentsAction::make(),
            DeleteAction::make(),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                CommentsEntry::make('filament_comments'),
            ]);
    }
}
