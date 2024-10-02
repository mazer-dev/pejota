<?php

namespace App\Filament\App\Resources;

use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\MenuGroupsEnum;
use App\Filament\App\Resources\InvoiceResource\Pages;
use App\Filament\App\Resources\InvoiceResource\RelationManagers;
use App\Helpers\PejotaHelper;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TabelaPreco;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::FINANCE->value);
    }

    public static function getModelLabel(): string
    {
        return __('Invoice');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(4)
            ->schema([
                Forms\Components\TextInput::make('number')
                    ->translateLabel()
                    ->required()
                    ->default(fn() => CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumberFormated()),
                Forms\Components\Select::make('status')
                    ->options(InvoiceStatusEnum::class)
                    ->default(fn() => InvoiceStatusEnum::DRAFT)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn(Forms\Set $set, $state) => $state == InvoiceStatusEnum::PAID->value ? $set('payment_date', now()->format(PejotaHelper::getUserDateFormat())) : null),
                Forms\Components\DatePicker::make('due_date')
                    ->translateLabel()
                    ->date(),
                Forms\Components\DatePicker::make('payment_date')
                    ->translateLabel()
                    ->date()
                    ->live()
                    ->required(fn(Forms\Get $get) => $get('status') == InvoiceStatusEnum::PAID->value),
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
                    ->columnSpan(2),
                Forms\Components\TextInput::make('discount')
                    ->numeric()
                    ->live()
                    ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) => self::calcItemTotal($get, $set)),
                Forms\Components\TextInput::make('total')
                    ->required()
                    ->numeric()
                    ->readOnly(),
                Forms\Components\Textarea::make('extra_info')
                    ->translateLabel()
                    ->columnSpan(2)
                    ->rows(3),
                Forms\Components\Textarea::make('obs_internal')
                    ->label('Internal observations')
                    ->translateLabel()
                    ->columnSpan(2)
                    ->rows(3),

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
                            ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) => self::calcItemTotal($get, $set)),
                        Forms\Components\TextInput::make('price')
                            ->translateLabel()
                            ->required()
                            ->numeric()
                            ->live()
                            ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) => self::calcItemTotal($get, $set)),
                        Forms\Components\TextInput::make('discount')
                            ->translateLabel()
                            ->numeric()
                            ->live()
                            ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) => self::calcItemTotal($get, $set)),
                        Forms\Components\TextInput::make('total')
                            ->translateLabel()
                            ->required()
                            ->numeric()
                            ->readOnly(),
                        Forms\Components\Textarea::make('obs')
                            ->translateLabel()
                            ->columnSpanFull(),
                    ])
                    ->deleteAction(function (Forms\Components\Actions\Action $action) {
                        // call cal total after delete a row item of the repeater
                        return $action->after(fn(Forms\Set $set, Forms\Get $get) => self::calcInvoiceTotal($get, $set));
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->translateLabel()
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('number')
                    ->translateLabel()
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->translateLabel()
                    ->searchable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->translateLabel()
                    ->wrapHeader()
                    ->alignCenter()
                    ->date(PejotaHelper::getUserDateFormat())
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->translateLabel()
                    ->wrapHeader()
                    ->alignCenter()
                    ->date(PejotaHelper::getUserDateFormat())
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount')
                    ->translateLabel()
                    ->numeric()
                    ->money()
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')
                    ->translateLabel()
                    ->weight(FontWeight::Bold)
                    ->numeric()
                    ->money()
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ]),
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

    public static function calcItemTotal(Forms\Get $get, Forms\Set $set)
    {
        $price = (float)str_replace(',', '.', $get('price'));
        $qty = (float)$get('quantity');

        $total = $price * $qty;

        $discount = (float)$get('discount');

        $total = round($total - $discount, 2);

        $set(
            'total',
            $total
        );

        self::calcInvoiceTotal($get, $set);
    }

    public static function calcInvoiceTotal(Forms\Get $get, Forms\Set $set)
    {
        $items = $get('../../items');
        $totalComponent = '../../total';
        $discountComponent = '../../discount';

        if ($items == null) {
            $items = $get('items');
            $totalComponent = 'total';
            $discountComponent = 'discount';
        }

        // the get up two levels to get items fom repeater .. remember we are in the repeater item here
        $totalInvoice = collect($items)
            ->pluck('total')
            ->sum();

        $discountValue = (float)$get($discountComponent);
        $invoiceValue = $totalInvoice - $discountValue;

        $set(
            $totalComponent,
            $invoiceValue
        );
    }
}
