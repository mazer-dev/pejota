<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Filament\App\Resources\TagResource\Pages\ListTags;
use App\Filament\App\Resources\TagResource\Pages\ViewTag;
use App\Filament\App\Resources\TagResource\RelationManagers\NotesRelationManager;
use App\Filament\App\Resources\TagResource\RelationManagers\ProjectsRelationManager;
use App\Filament\App\Resources\TagResource\RelationManagers\TasksRelationManager;
use App\Models\Tag;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::SETTINGS->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('name')
                    ->translateLabel()
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('slug')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('type')
                    ->translateLabel(),
                TextInput::make('order_column')
                    ->translateLabel()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('order_column')
                    ->label('Order')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('type')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                //                Tables\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
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
            TasksRelationManager::class,
            ProjectsRelationManager::class,
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTags::route('/'),
            //            'create' => Pages\CreateTag::route('/create'),
            'view' => ViewTag::route('/{record}'),
            //            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
