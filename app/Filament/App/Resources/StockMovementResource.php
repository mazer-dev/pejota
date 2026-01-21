<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Movement Details')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Movement Type')
                            ->options([
                                'in' => '📥 Stock In (Receive)',
                                'out' => '📤 Stock Out (Issue)',
                                'transfer' => '🔄 Transfer',
                                'adjustment_add' => '➕ Adjustment (Add)',
                                'adjustment_subtract' => '➖ Adjustment (Subtract)',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('material_id')
                            ->label('Material')
                            ->relationship('material', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('qty')
                            ->label('Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                    ])->columns(3),

                Forms\Components\Section::make('Location')
                    ->schema([
                        Forms\Components\Select::make('from_branch_id')
                            ->label('From Branch/Warehouse')
                            ->relationship('fromBranch', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['out', 'transfer']))
                            ->required(fn (Forms\Get $get) => in_array($get('type'), ['out', 'transfer'])),
                        
                        Forms\Components\Select::make('to_branch_id')
                            ->label('To Branch/Warehouse')
                            ->relationship('toBranch', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['in', 'transfer']))
                            ->required(fn (Forms\Get $get) => in_array($get('type'), ['in', 'transfer'])),

                        Forms\Components\Select::make('project_id')
                            ->label('Project')
                            ->relationship('project', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get) => $get('type') === 'out'),
                    ])->columns(3),

                Forms\Components\Section::make('Additional Info')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference / PO Number'),

                        Forms\Components\Textarea::make('reason')
                            ->label('Reason / Notes')
                            ->rows(2),

                        Forms\Components\DatePicker::make('movement_date')
                            ->label('Date')
                            ->default(now())
                            ->required(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        'transfer' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('material.name')
                    ->label('Material')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('qty')
                    ->label('Quantity')
                    ->numeric(decimalPlaces: 2),

                Tables\Columns\TextColumn::make('fromBranch.name')
                    ->label('From')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('toBranch.name')
                    ->label('To')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->toggleable(),
            ])
            ->defaultSort('movement_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'in' => 'Stock In',
                        'out' => 'Stock Out',
                        'transfer' => 'Transfer',
                    ]),
                Tables\Filters\SelectFilter::make('material')
                    ->relationship('material', 'name'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'create' => Pages\CreateStockMovement::route('/create'),
            'edit' => Pages\EditStockMovement::route('/{record}/edit'),
        ];
    }
}