<?php

namespace App\Filament\App\Resources;

use App\Enums\FeatureEnum;
use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Concerns\GatesAccessByFeature;
use App\Filament\App\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\App\Resources\ProductResource\Pages\EditProduct;
use App\Filament\App\Resources\ProductResource\Pages\ListProducts;
use App\Filament\App\Resources\ProductResource\Pages\ViewProduct;
use App\Models\Product;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    use GatesAccessByFeature;

    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?int $navigationSort = MenuSortEnum::PRODUCTS->value;

    public static function feature(): FeatureEnum
    {
        return FeatureEnum::Products;
    }

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
                Select::make('unit_id')
                    ->translateLabel()
                    ->relationship('unit', 'name'),
                Textarea::make('description')
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
                Checkbox::make('service')
                    ->translateLabel(),
                Checkbox::make('digital')
                    ->translateLabel(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                IconColumn::make('service')
                    ->translateLabel()
                    ->boolean(),
                TextColumn::make('unit.name')
                    ->translateLabel(),
                TextColumn::make('price')
                    ->translateLabel()
                    ->money(),
            ])
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
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
