<?php

namespace App\Filament\App\Resources;

use App\Enums\StatusPhaseEnum;
use App\Filament\App\Resources\StatusResource\Pages;
use App\Helpers\PejotaHelper;
use App\Models\Status;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StatusResource extends Resource
{
    protected static ?string $model = Status::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->translateLabel()
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('description')
                    ->translateLabel()
                    ->columnSpanFull(),
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\ColorPicker::make('color')
                        ->translateLabel()
                        ->default('#6abeed')
                        ->required(),
                    Forms\Components\TextInput::make('sort_order')
                        ->translateLabel()
                        ->required()
                        ->numeric()
                        ->default(0),
                    Forms\Components\Select::make('phase')
                        ->translateLabel()
                        ->options(StatusPhaseEnum::class)
                        ->required(),
                    Forms\Components\Toggle::make('active')
                        ->translateLabel()
                        ->required()
                        ->default(true),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->translateLabel()
                    ->searchable(),
                Tables\Columns\ColorColumn::make('color')
                    ->translateLabel(),
                Tables\Columns\TextColumn::make('phase')
                    ->translateLabel()
                    ->searchable(),
                Tables\Columns\IconColumn::make('active')
                    ->translateLabel()
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
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
            'index' => Pages\ListStatuses::route('/'),
            'create' => Pages\CreateStatus::route('/create'),
            'edit' => Pages\EditStatus::route('/{record}/edit'),
        ];
    }
}
