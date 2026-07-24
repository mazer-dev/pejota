<?php

namespace App\Filament\App\Resources;

use App\Enums\FeatureEnum;
use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Exceptions\MissingExchangeRateException;
use App\Filament\App\Concerns\GatesAccessByFeature;
use App\Filament\App\Resources\ExchangeRateResource\Pages\ListExchangeRates;
use App\Helpers\PejotaHelper;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExchangeRateResource extends Resource
{
    use GatesAccessByFeature;

    protected static ?string $model = ExchangeRate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = MenuSortEnum::EXCHANGE_RATES->value;

    public static function feature(): FeatureEnum
    {
        return FeatureEnum::MultiCurrency;
    }

    public static function getModelLabel(): string
    {
        return __('Exchange rate');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::FINANCE->value);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('currency_code')
                    ->label(__('Currency'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->translateLabel()
                    ->date(PejotaHelper::getUserDateFormat())
                    ->sortable(),
                TextColumn::make('rate')
                    ->label(__('1 USD ='))
                    ->numeric(decimalPlaces: 6)
                    ->sortable(),
                TextColumn::make('base_value')
                    ->label(__('In base currency'))
                    ->getStateUsing(function (ExchangeRate $record): string {
                        try {
                            $value = app(ExchangeRateService::class)->convert(
                                1.0,
                                $record->currency_code,
                                PejotaHelper::getUserCurrency(),
                                $record->date,
                            );

                            return number_format($value, 6);
                        } catch (MissingExchangeRateException) {
                            return '—';
                        }
                    }),
                TextColumn::make('source')
                    ->label(__('Source'))
                    ->badge(),
                IconColumn::make('is_frozen')
                    ->label(__('Frozen'))
                    ->boolean(),
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
                SelectFilter::make('currency_code')
                    ->label(__('Currency'))
                    ->options(fn (): array => Currency::query()->orderBy('code')->pluck('code', 'code')->all()),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label(__('From')),
                        DatePicker::make('until')->label(__('Until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->recordAction('view')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('currency_code')->label(__('Currency')),
            TextEntry::make('date')->translateLabel()->date(PejotaHelper::getUserDateFormat()),
            TextEntry::make('rate')->label(__('1 USD =')),
            TextEntry::make('source')->label(__('Source'))->badge(),
            IconEntry::make('is_frozen')->label(__('Frozen'))->boolean(),
            TextEntry::make('created_at')->translateLabel()->dateTime(),
            TextEntry::make('updated_at')->translateLabel()->dateTime(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExchangeRates::route('/'),
        ];
    }
}
