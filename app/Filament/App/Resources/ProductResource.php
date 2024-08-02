<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Filament\App\Resources\ProductResource\Pages;
use App\Filament\App\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';
    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return __('Product');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::ADMINISTRATION->value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->translateLabel(),
                Forms\Components\Select::make('unit_id')
                    ->translateLabel()
                    ->relationship('unit', 'name'),
                Forms\Components\Textarea::make('description')
                    ->translateLabel()
                    ->rows(6)
                    ->columnSpanFull(),
                TextInput::make('cost')
                    ->translateLabel()
                    ->prefixIcon('heroicon-o-currency-dollar')
                    ->required()
                    ->numeric(),
                TextInput::make('price')
                    ->translateLabel()
                    ->prefixIcon('heroicon-o-currency-dollar')
                    ->required()
                    ->numeric(),
                Forms\Components\Checkbox::make('service')
                    ->translateLabel(),
                Forms\Components\Checkbox::make('digital')
                    ->translateLabel(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('service')
                    ->translateLabel()
                    ->boolean(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->translateLabel(),
                Tables\Columns\TextColumn::make('price')
                    ->translateLabel()
                    ->money()
            ])
            ->filters([
                //
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
