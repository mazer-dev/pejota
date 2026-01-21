<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\MaterialResource\Pages;
use App\Filament\App\Resources\MaterialResource\RelationManagers;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

   public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('sku')
                        ->label('SKU')
                        ->unique(ignoreRecord: true)
                        ->required(),
                    Forms\Components\TextInput::make('barcode')
                        ->label('Barcode/QR Code')
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('unit')
                        ->options([
                            'kg' => 'Kilogram (kg)',
                            'm3' => 'Cubic Meter (m³)',
                            'm2' => 'Square Meter (m²)',
                            'm' => 'Meter (m)',
                            'ton' => 'Ton',
                            'bag' => 'Bag',
                            'unit' => 'Unit/Piece',
                            'liter' => 'Liter',
                            'box' => 'Box',
                        ])
                        ->required(),
                ])->columns(2),

            Forms\Components\Section::make('Pricing & Inventory')
                ->schema([
                    Forms\Components\TextInput::make('cost_price')
                        ->label('Cost Price')
                        ->numeric()
                        ->prefix('$'),
                    Forms\Components\TextInput::make('sale_price')
                        ->label('Sale Price')
                        ->numeric()
                        ->prefix('$'),
                    Forms\Components\TextInput::make('vat_rate')
                        ->label('VAT %')
                        ->numeric()
                        ->default(0)
                        ->suffix('%'),
                    Forms\Components\TextInput::make('reorder_point')
                        ->label('Reorder Point')
                        ->numeric()
                        ->default(0)
                        ->helperText('Alert when stock falls below this level'),
                ])->columns(4),

            Forms\Components\Section::make('Supplier')
                ->schema([
                    Forms\Components\Select::make('supplier_id')
                        ->relationship('supplier', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required(),
                            Forms\Components\TextInput::make('contact'),
                            Forms\Components\TextInput::make('phone'),
                            Forms\Components\TextInput::make('email')->email(),
                        ]),
                ])->columns(1),
        ]);
}
    public static function table(Table $table): Table
    {
        return $table
            ->columns(components: [
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('unit')->badge()->color('gray'),
            Tables\Columns\TextColumn::make('cost_price')->money('usd'),
            // Custom Column for current stock (derived from StockMovements)
            Tables\Columns\TextColumn::make('stock_level')
                ->label('On Hand')
                ->state(fn (Material $record): float => $record->stock_level)
                ->numeric(decimalPlaces: 2)
                ->color(fn (float $state): string => $state <= 0 ? 'danger' : 'success')
                ->suffix(fn (Material $record): string => " {$record->unit}")
                ->getStateUsing(function (Material $record) {
                    $in = \App\Models\StockMovement::where('material_id', $record->id)->where('type', 'in')->sum('qty');
                    $out = \App\Models\StockMovement::where('material_id', $record->id)->where('type', 'out')->sum('qty');
                    return $in - $out;
                })
                ->badge()
                ->color(fn ($state) => $state <= 0 ? 'danger' : 'success'),
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
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}
