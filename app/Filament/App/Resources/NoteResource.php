<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\NoteResource\Pages;
use App\Filament\App\Resources\NoteResource\RelationManagers;
use App\Models\Note;
use App\Tables\Columns\BlockTypesBadge;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\SpatieTagsInput::make('tags'),

                Forms\Components\Builder::make('content')
                    ->columnSpanFull()
                    ->blocks([
                        Forms\Components\Builder\Block::make('link')
                            ->columns(2)
                            ->inlineLabel()
                            ->icon('heroicon-o-link')
                            ->schema([
                                Forms\Components\TextInput::make('url')
                                    ->hiddenLabel()
                                    ->placeholder('url')
                                    ->required()
                                    ->prefixAction(
                                        fn ($state) => Forms\Components\Actions\Action::make('url')
                                            ->url($state)
                                            ->openUrlInNewTab()
                                            ->icon('heroicon-o-link')
                                    ),
                                Forms\Components\TextInput::make('title')
                                    ->hiddenLabel()
                                    ->placeholder('Title'),
                            ])
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),

                BlockTypesBadge::make('content')
                    ->color(Color::Cyan),

                Tables\Columns\SpatieTagsColumn::make('tags'),
            ])
            ->filters([

            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotes::route('/'),
            'create' => Pages\CreateNote::route('/create'),
            'view' => Pages\ViewNote::route('/{record}'),
            'edit' => Pages\EditNote::route('/{record}/edit'),
        ];
    }
}
