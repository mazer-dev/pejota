<?php

namespace App\Filament\App\Resources\TagResource\RelationManagers;

use App\Filament\App\Resources\NoteResource;
use App\Models\Note;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    public function infolist(Infolist $infolist): Infolist
    {
        return NoteResource::infolist($infolist);
    }

    public function table(Table $table): Table
    {
        $table = NoteResource::table($table);

        $table->getColumn('title')
            ->url(fn (Note $record) => NoteResource\Pages\ViewNote::getUrl([$record]));

        $table->actions([
            Tables\Actions\ViewAction::make()->url(fn (Note $record) => NoteResource\Pages\ViewNote::getUrl([$record]))
        ]);

        return $table;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->notes->count();

        return $count > 0 ? $count : null;
    }
}
