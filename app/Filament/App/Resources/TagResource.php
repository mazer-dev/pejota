<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Filament\App\Resources\TagResource\Pages;
use App\Filament\App\Resources\TagResource\RelationManagers;
use App\Models\Tag;
use App\Providers\Filament\AppPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::SETTINGS->value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('name')
                    ->translateLabel()
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('slug')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('type')
                    ->translateLabel(),
                Forms\Components\TextInput::make('order_column')
                    ->translateLabel()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('order_column')
                    ->label('Order')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->translateLabel()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                //                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->columns(1)
            ->schema([
                Section::make('')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('name')
                            ->translateLabel(),
                        TextEntry::make('slug'),
                        TextEntry::make('order_column')
                            ->label('Order'),
                        TextEntry::make('type')
                            ->translateLabel(),

                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TasksRelationManager::class,
            RelationManagers\ProjectsRelationManager::class,
            RelationManagers\NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTags::route('/'),
            //            'create' => Pages\CreateTag::route('/create'),
            'view' => Pages\ViewTag::route('/{record}'),
            //            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
