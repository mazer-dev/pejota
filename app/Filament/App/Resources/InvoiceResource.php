<?php

namespace App\Filament\App\Resources;

use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\MenuGroupsEnum;
use App\Exceptions\MissingExchangeRateException;
use App\Filament\App\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Filament\App\Resources\InvoiceResource\Pages\EditInvoice;
use App\Filament\App\Resources\InvoiceResource\Pages\ListInvoices;
use App\Filament\App\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Filament\App\Widgets\InvoicesOverview;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\ExchangeRateService;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
//            ->columns(4)
            ->schema([
                Grid::make([
                    'default' => 2,
                    'md' => 4,
                ])->schema([
                    TextInput::make('number')
                        ->translateLabel()
                        ->disabled()
                        ->dehydrated(false)
                        ->default(fn () => CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->peekNextDocNumberFormated()),
                    Select::make('status')
                        ->options(InvoiceStatusEnum::class)
                        ->default(fn () => InvoiceStatusEnum::DRAFT)
                        ->required()
                        ->live()
                        ->afterStateUpdated(
                            fn (Set $set, $state) => $state == InvoiceStatusEnum::PAID->value ? $set(
                                'payment_date',
                                now()->format(PejotaHelper::getUserDateFormat())
                            ) : null
                        ),
                    DatePicker::make('due_date')
                        ->translateLabel()
                        ->date(),
                    DatePicker::make('payment_date')
                        ->translateLabel()
                        ->date()
                        ->live()
                        ->required(fn (Get $get) => $get('status') == InvoiceStatusEnum::PAID->value),
                    Select::make('client_id')
                        ->translateLabel()
                        ->required()
                        ->relationship('client', 'name')
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state): void {
                            $set('currency', Client::find($state)?->currency ?? PejotaHelper::getUserCurrency());
                        }),
                    Select::make('currency')
                        ->translateLabel()
                        ->required()
                        ->options(fn (): array => Currency::selectOptions(PejotaHelper::getUserCurrency()))
                        ->default(fn (): string => PejotaHelper::getUserCurrency())
                        ->searchable()
                        ->helperText(__('Item prices are entered in this currency; changing it does not convert values.')),
                    Select::make('project_id')
                        ->translateLabel()
                        ->relationship('project', 'name')
                        ->searchable(),
                    Select::make('contract_id')
                        ->translateLabel()
                        ->relationship('contract', 'title')
                        ->searchable(),
                    TextInput::make('title')
                        ->translateLabel()
                        ->required()
                        ->columnSpan(2),
                    TextInput::make('discount')
                        ->translateLabel()
                        ->numeric()
                        ->live()
                        ->afterStateUpdated(fn (Set $set, Get $get) => self::calcItemTotal($get, $set)),
                    TextInput::make('total')
                        ->required()
                        ->numeric()
                        ->readOnly(),
                    Textarea::make('extra_info')
                        ->translateLabel()
                        ->columnSpan(2)
                        ->rows(3),
                    Textarea::make('obs_internal')
                        ->label('Internal observations')
                        ->translateLabel()
                        ->columnSpan(2)
                        ->rows(3),

                    Repeater::make('items')
                        ->relationship()
                        ->columnSpanFull()
                        ->columns([
                            'default' => 2,
                            'md' => 5,
                        ])
                        ->schema([
                            Select::make('product_id')
                                ->label('Reference product')
                                ->translateLabel()
                                ->relationship('product', 'name')
                                ->required()
                                ->columnSpan([
                                    'default' => 2,
                                ])
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
                            TextInput::make('name')
                                ->label('Description at invoice')
                                ->translateLabel()
                                ->columnSpan(3)
                                ->required(),
                            Select::make('unit_id')
                                ->translateLabel()
                                ->relationship('unit', 'name')
                                ->placeholder('Select')
                                ->required()
                                ->preload()
                                ->searchable(),
                            TextInput::make('quantity')
                                ->translateLabel()
                                ->required()
                                ->numeric()
                                ->live()
                                ->afterStateUpdated(
                                    fn (Set $set, Get $get) => self::calcItemTotal($get, $set)
                                ),
                            TextInput::make('price')
                                ->translateLabel()
                                ->required()
                                ->numeric()
                                ->live()
                                ->afterStateUpdated(
                                    fn (Set $set, Get $get) => self::calcItemTotal($get, $set)
                                ),
                            TextInput::make('discount')
                                ->translateLabel()
                                ->numeric()
                                ->live()
                                ->afterStateUpdated(
                                    fn (Set $set, Get $get) => self::calcItemTotal($get, $set)
                                ),
                            TextInput::make('total')
                                ->translateLabel()
                                ->required()
                                ->numeric()
                                ->readOnly(),
                            Textarea::make('obs')
                                ->translateLabel()
                                ->columnSpanFull(),
                        ])
                        ->deleteAction(function (Action $action) {
                            // call cal total after delete a row item of the repeater
                            return $action->after(
                                fn (Set $set, Get $get) => self::calcInvoiceTotal($get, $set)
                            );
                        }),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->groups([
                Group::make('due_date')
                    ->label(__('Due month'))
                    ->getKeyFromRecordUsing(fn (Invoice $record): string => $record->due_date?->format('Y-m') ?? '')
                    ->getTitleFromRecordUsing(fn (Invoice $record): string => $record->due_date?->translatedFormat('F Y') ?? __('No due date'))
                    ->groupQueryUsing(fn (QueryBuilder $query): QueryBuilder => $query->groupByRaw(
                        self::monthYearGroupExpression($query->getConnection()->getDriverName(), 'due_date')
                    )),
            ])
            ->striped()
            ->columns([
                TextColumn::make('status')
                    ->translateLabel()
                    ->badge()
                    ->searchable(),
                IconColumn::make('overdue_status')
                    ->label('')
                    ->translateLabel()
                    ->wrapHeader()
                    ->alignCenter()
                    ->sortable()
                    ->icon(fn ($record) => match ($record->is_overdue) {
                        true => 'heroicon-o-exclamation-circle',
                        default => null,
                    })
                    ->color(fn ($record) => match ($record->is_overdue) {
                        true => 'danger',
                        default => null,
                    })
                    ->getStateUsing(fn ($record) => $record->is_overdue),
                TextColumn::make('number')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('title')
                    ->translateLabel()
                    ->wrap()
                    ->searchable(),
                TextColumn::make('client.name')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->translateLabel()
                    ->wrapHeader()
                    ->alignCenter()
                    ->date(PejotaHelper::getUserDateFormat())
                    ->sortable(),
                TextColumn::make('payment_date')
                    ->translateLabel()
                    ->wrapHeader()
                    ->alignCenter()
                    ->date(PejotaHelper::getUserDateFormat())
                    ->sortable(),
                TextColumn::make('discount')
                    ->translateLabel()
                    ->numeric()
                    ->money()
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('total')
                    ->translateLabel()
                    ->weight(FontWeight::Bold)
                    ->numeric()
                    ->money(fn (Invoice $record): string => $record->currency ?? PejotaHelper::getUserCurrency())
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('currency')
                    ->translateLabel()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('base_total')
                    ->label(__('Base value'))
                    ->getStateUsing(function (Invoice $record): ?float {
                        try {
                            return $record->baseTotal;
                        } catch (MissingExchangeRateException) {
                            return null;
                        }
                    })
                    ->money(PejotaHelper::getUserCurrency())
                    ->placeholder('—')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client')
                    ->translateLabel()
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->translateLabel()
                    ->options(InvoiceStatusEnum::class)
                    ->multiple(),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Tables\Actions\Action::make('change_status')
                        ->label(__('Change status'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->fillForm(fn (Invoice $record): array => [
                            'status' => $record->status->value,
                            'payment_date' => $record->payment_date?->format('Y-m-d'),
                        ])
                        ->form([
                            ToggleButtons::make('status')
                                ->translateLabel()
                                ->options(InvoiceStatusEnum::class)
                                ->inline()
                                ->required()
                                ->live()
                                ->columnSpanFull()
                                ->afterStateUpdated(function (Invoice $record, Set $set, Get $get, $state): void {
                                    if ($state === InvoiceStatusEnum::PAID->value) {
                                        if (blank($get('payment_date'))) {
                                            $set('payment_date', self::defaultPaidDate($record));
                                        }
                                        $effectiveDate = filled($get('payment_date')) ? $get('payment_date') : self::defaultPaidDate($record);
                                        if (self::isForeignInvoice($record) && blank($get('realized_rate'))) {
                                            $set('realized_rate', self::referenceRate($record, $effectiveDate));
                                        }
                                    } elseif (self::isUnpaidStatus($state)) {
                                        $set('payment_date', null);
                                    }
                                }),
                            Grid::make(3)->schema([
                                Placeholder::make('total_display')
                                    ->label(__('Total'))
                                    ->content(fn (Invoice $record): string => Number::currency(
                                        $record->total ?? 0,
                                        $record->currency ?? self::baseCurrency(),
                                        PejotaHelper::getUserLocate(),
                                    )),
                                Placeholder::make('base_total_display')
                                    ->label(__('Base value'))
                                    ->content(function (Invoice $record, Get $get): string {
                                        $rate = $get('realized_rate');

                                        if ($get('status') === InvoiceStatusEnum::PAID->value && self::isForeignInvoice($record) && filled($rate)) {
                                            return Number::currency((float) $record->total * (float) $rate, self::baseCurrency(), PejotaHelper::getUserLocate());
                                        }

                                        try {
                                            return Number::currency($record->baseTotal, self::baseCurrency(), PejotaHelper::getUserLocate());
                                        } catch (MissingExchangeRateException) {
                                            return '—';
                                        }
                                    }),
                                Placeholder::make('due_date_display')
                                    ->label(__('Due date'))
                                    ->content(fn (Invoice $record): string => $record->due_date?->format(PejotaHelper::getUserDateFormat()) ?? '—'),
                                DatePicker::make('payment_date')
                                    ->translateLabel()
                                    ->date()
                                    ->live()
                                    ->dehydrated()
                                    ->disabled(fn (Get $get): bool => self::isUnpaidStatus($get('status')))
                                    ->afterStateUpdated(function (Invoice $record, Set $set, Get $get, $state): void {
                                        if ($get('status') === InvoiceStatusEnum::PAID->value && self::isForeignInvoice($record)) {
                                            $rate = self::referenceRate($record, $state);
                                            if ($rate !== null) {
                                                $set('realized_rate', $rate);
                                            }
                                        }
                                    }),
                                TextInput::make('realized_rate')
                                    ->label(fn (Invoice $record): string => __('Realized rate').' (1 '.($record->currency ?? self::baseCurrency()).' = ? '.self::baseCurrency().')')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('any')
                                    ->live(onBlur: true)
                                    ->visible(fn (Invoice $record, Get $get): bool => $get('status') === InvoiceStatusEnum::PAID->value && self::isForeignInvoice($record))
                                    ->required(fn (Invoice $record, Get $get): bool => $get('status') === InvoiceStatusEnum::PAID->value && self::isForeignInvoice($record))
                                    ->helperText(__('Freezes the base-currency value received for this invoice.')),
                            ]),
                        ])
                        ->action(function (Invoice $record, array $data): void {
                            $status = $data['status'];
                            $paymentDate = $data['payment_date'] ?? null;

                            if (self::isUnpaidStatus($status)) {
                                $paymentDate = null;
                            } elseif ($status === InvoiceStatusEnum::PAID->value && blank($paymentDate)) {
                                $paymentDate = $record->payment_date?->format('Y-m-d') ?? self::defaultPaidDate($record);
                            }

                            $payload = ['status' => $status, 'payment_date' => $paymentDate];

                            if ($status === InvoiceStatusEnum::PAID->value && filled($data['realized_rate'] ?? null)) {
                                $payload['exchange_rate'] = (float) $data['realized_rate'];
                            }

                            $record->update($payload);
                        }),
                    Tables\Actions\Action::make('pdf')
                        ->label('PDF')
                        ->color('info')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(fn ($record) => self::generatePdf($record)),
                    Tables\Actions\Action::make('clone')
                        ->translateLabel()
                        ->color('gray')
                        ->icon('heroicon-o-document-duplicate')
                        ->url(fn ($record) => static::getUrl('create', ['clone' => $record->id])),
                ]),
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
            //            RelationManagers\ItemRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            InvoicesOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function monthYearGroupExpression(string $driver, string $column): string
    {
        return match ($driver) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    public static function calcItemTotal(Get $get, Set $set)
    {
        $price = (float) str_replace(',', '.', $get('price'));
        $qty = (float) $get('quantity');

        $total = $price * $qty;

        $discount = (float) $get('discount');

        $total = round($total - $discount, 2);

        $set(
            'total',
            $total
        );

        self::calcInvoiceTotal($get, $set);
    }

    public static function calcInvoiceTotal(Get $get, Set $set)
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

        $discountValue = (float) $get($discountComponent);
        $invoiceValue = $totalInvoice - $discountValue;

        $set(
            $totalComponent,
            $invoiceValue
        );
    }

    private static function baseCurrency(): string
    {
        return PejotaHelper::getUserCurrency();
    }

    private static function isForeignInvoice(Invoice $record): bool
    {
        return ($record->currency ?? self::baseCurrency()) !== self::baseCurrency();
    }

    private static function referenceRate(Invoice $record, ?string $date): ?float
    {
        if (! self::isForeignInvoice($record)) {
            return null;
        }

        try {
            return app(ExchangeRateService::class)->convert(
                1.0,
                $record->currency,
                self::baseCurrency(),
                $date ? CarbonImmutable::parse($date) : CarbonImmutable::now(PejotaHelper::getUserTimeZone()),
            );
        } catch (MissingExchangeRateException) {
            return null;
        }
    }

    private static function isUnpaidStatus(?string $status): bool
    {
        return in_array($status, [
            InvoiceStatusEnum::UNPAID->value,
            InvoiceStatusEnum::CANCELED->value,
        ], true);
    }

    private static function defaultPaidDate(Invoice $invoice): string
    {
        return ($invoice->due_date ?? now())->format('Y-m-d');
    }

    public static function generatePdf(Invoice $invoice): StreamedResponse
    {
        return response()->streamDownload(function () use ($invoice) {
            $pdf = (new InvoiceService)->generatePdf($invoice);
            echo $pdf->stream();
        }, Str::snake($invoice->client->name).'_invoice_'.$invoice->number.'.pdf');
    }
}
