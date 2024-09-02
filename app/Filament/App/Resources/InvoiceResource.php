<?php

namespace App\Filament\App\Resources;

use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Filament\App\Resources\InvoiceResource\Pages;
use App\Filament\App\Resources\InvoiceResource\RelationManagers;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TabelaPreco;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getModelLabel(): string
    {
        return __('Invoice');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(3)
            ->schema([
                Forms\Components\TextInput::make('number')
                    ->translateLabel()
                    ->required()
                    ->default(fn() => CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumberFormated()),
                Forms\Components\Select::make('status')
                    ->options(InvoiceStatusEnum::class)
                    ->default(fn() => InvoiceStatusEnum::DRAFT)
                    ->required(),
                Forms\Components\DatePicker::make('due_date')
                    ->translateLabel()
                    ->date(),
                Forms\Components\Select::make('client_id')
                    ->translateLabel()
                    ->required()
                    ->relationship('client', 'name')
                    ->searchable(),
                Forms\Components\Select::make('project_id')
                    ->translateLabel()
                    ->relationship('project', 'name')
                    ->searchable(),
                Forms\Components\Select::make('contract_id')
                    ->translateLabel()
                    ->relationship('contract', 'title')
                    ->searchable(),
                Forms\Components\TextInput::make('title')
                    ->translateLabel()
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('extra_info')
                    ->translateLabel()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('obs_internal')
                    ->label('Internal observations')
                    ->translateLabel()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('discount')
                    ->numeric(),
                Forms\Components\TextInput::make('total')
                    ->required()
                    ->numeric()
                    ->readOnly(),

                Forms\Components\Repeater::make('items')
                    ->relationship()
                    ->columnSpanFull()
                    ->columns(5)
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Reference product')
                            ->translateLabel()
                            ->relationship('product', 'name')
                            ->required()
                            ->columnSpan(2)
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $product = Product::find($state);
                                    if ($product) {
                                        $set('name', $product->name);
                                        $set('obs', $product->description);
                                        $set('unit_id', $product->unit_id);
                                        $set('price', $product->price);
                                    }
                                }
                            }),
                        Forms\Components\TextInput::make('name')
                            ->label('Description at invoice')
                            ->translateLabel()
                            ->columnSpan(3)
                            ->required(),
                        Forms\Components\Select::make('unit_id')
                            ->translateLabel()
                            ->relationship('unit', 'name')
                            ->placeholder('Select')
                            ->required()
                            ->preload()
                            ->searchable(),
                        Forms\Components\TextInput::make('quantity')
                            ->translateLabel()
                            ->required()
                            ->numeric()
                            ->live()
                            ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) => self::calcTotal($get, $set)),
                        Forms\Components\TextInput::make('price')
                            ->translateLabel()
                            ->required()
                            ->numeric()
                            ->live()
                            ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) => self::calcTotal($get, $set)),
                        Forms\Components\TextInput::make('discount')
                            ->translateLabel()
                            ->numeric()
                            ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) => self::calcTotal($get, $set)),
                        Forms\Components\TextInput::make('total')
                            ->translateLabel()
                            ->required()
                            ->numeric()
                            ->readOnly(),
                        Forms\Components\Textarea::make('obs')
                            ->translateLabel()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.title')
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract.title')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('discount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->numeric()
                    ->sortable(),
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
//            RelationManagers\ItemRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function calcTotal(Forms\Get $get, Forms\Set $set)
    {
        $price = (float)str_replace(',', '.', $get('price'));
        $qty = (float)$get('quantity');
        $discount = (float)$get('discount') ?? 0;

        $total = round(($price * $qty) * (100 - $discount) / 100, 2);

        $set(
            'total',
            $total
        );

        // calculate now total of invoice
        // the get up two levels to get items fom repeater .. remember we are in the repeater item here
        $totalInvoice = collect($get('../../items'))
            ->pluck('total')
            ->sum();

        $set(
            '../../total',
            $totalInvoice
        );
    }
}
