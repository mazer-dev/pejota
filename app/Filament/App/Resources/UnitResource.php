<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\UnitResource\Pages\CreateUnit;
use App\Filament\App\Resources\UnitResource\Pages\EditUnit;
use App\Filament\App\Resources\UnitResource\Pages\ListUnits;
use App\Filament\App\Resources\UnitResource\Pages\ViewUnit;
use App\Models\Unit;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-pointing-out';

    public static function getModelLabel(): string
    {
        return __('Unit');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->translateLabel()
                    ->required(),
                TextInput::make('symbol')
                    ->translateLabel()
                    ->required(),

                Textarea::make('description')
                    ->translateLabel()
                    ->rows(5)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('name')
                    ->translateLabel()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('symbol')
                    ->translateLabel()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('description')
                    ->translateLabel(),
            ])
            ->defaultSort('name')
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListUnits::route('/'),
            'create' => CreateUnit::route('/create'),
            'view' => ViewUnit::route('/{record}'),
            'edit' => EditUnit::route('/{record}/edit'),
        ];
    }
}
