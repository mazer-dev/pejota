<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialResource\Pages;
use App\Filament\Resources\MaterialResource\RelationManagers;
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
            Forms\Components\TextInput::make('sku')
                ->label('Material Code (SKU)')
                ->unique(ignoreRecord: true)
                ->required(), // Unique identifier for the material
            Forms\Components\TextInput::make('name')
                ->required(), // Material name
            Forms\Components\Select::make('unit')
                ->options([
                    'kg' => 'Kilogram',
                    'm3' => 'Cubic Meter',
                    'ton' => 'Ton',
                    'bag' => 'Bag',
                    'piece' => 'Piece',
                ])
                ->required(), // Unit of measurement
            Forms\Components\TextInput::make('cost_price')
                ->numeric()
                ->prefix('$'), // Buying price
        ]);
}
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
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
