<?php

namespace App\Filament\App\Resources;

use App\Enums\StatusPhaseEnum;
use App\Filament\App\Resources\StatusResource\Pages\CreateStatus;
use App\Filament\App\Resources\StatusResource\Pages\EditStatus;
use App\Filament\App\Resources\StatusResource\Pages\ListStatuses;
use App\Helpers\PejotaHelper;
use App\Models\Status;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusResource extends Resource
{
    protected static ?string $model = Status::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->translateLabel()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('description')
                    ->translateLabel()
                    ->columnSpanFull(),
                Grid::make(4)->schema([
                    ColorPicker::make('color')
                        ->translateLabel()
                        ->default('#6abeed')
                        ->required(),
                    TextInput::make('sort_order')
                        ->translateLabel()
                        ->required()
                        ->numeric()
                        ->default(0),
                    Select::make('phase')
                        ->translateLabel()
                        ->options(StatusPhaseEnum::class)
                        ->required(),
                    Toggle::make('active')
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
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->translateLabel()
                    ->searchable(),
                ColorColumn::make('color')
                    ->translateLabel(),
                TextColumn::make('phase')
                    ->translateLabel()
                    ->searchable(),
                IconColumn::make('active')
                    ->translateLabel()
                    ->boolean(),
                TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
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
            'index' => ListStatuses::route('/'),
            'create' => CreateStatus::route('/create'),
            'edit' => EditStatus::route('/{record}/edit'),
        ];
    }
}
