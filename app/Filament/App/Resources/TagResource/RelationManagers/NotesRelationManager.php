<?php

namespace App\Filament\App\Resources\TagResource\RelationManagers;

use App\Filament\App\Resources\NoteResource;
use App\Filament\App\Resources\NoteResource\Pages\ViewNote;
use App\Models\Note;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    public function infolist(Schema $schema): Schema
    {
        return NoteResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        $table = NoteResource::table($table);

        $table->getColumn('title')
            ->url(fn (Note $record) => ViewNote::getUrl([$record]));

        $table->recordActions([
            ViewAction::make()->url(fn (Note $record) => ViewNote::getUrl([$record])),
        ]);

        return $table;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->notes->count();

        return $count > 0 ? $count : null;
    }
}
