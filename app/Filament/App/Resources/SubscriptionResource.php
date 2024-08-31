<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Enums\SubscriptionBillingPeriodEnum;
use App\Enums\SubscriptionStatusEnum;
use App\Filament\App\Resources\SubscriptionResource\Pages;
use App\Filament\App\Resources\SubscriptionResource\RelationManagers;
use App\Helpers\PejotaHelper;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-tv';

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::FINANCE->value);
    }

    public static function getModelLabel(): string
    {
        return __('Subscription');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('service')
                    ->columnSpan(2)
                    ->translateLabel()
                    ->required(),
                Forms\Components\TextInput::make('price')
                    ->translateLabel()
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                Forms\Components\TextInput::make('currency')
                    ->translateLabel()
                    ->required(),
                Forms\Components\TextInput::make('payment_method')
                    ->translateLabel()
                    ->required(),
                Forms\Components\TextInput::make('payment_info')
                    ->label('Payment extra-info')
                    ->translateLabel(),
                Forms\Components\Select::make('status')
                    ->options(SubscriptionStatusEnum::class)
                    ->translateLabel()
                    ->required(),
                Forms\Components\Select::make('billing_period')
                    ->options(SubscriptionBillingPeriodEnum::class)
                    ->translateLabel()
                    ->required(),
                Forms\Components\DatePicker::make('trial_ends_at')
                    ->translateLabel()
                    ->required(fn(Forms\Get $get) => $get('status') == SubscriptionStatusEnum::TRIAL->value),
                Forms\Components\DatePicker::make('canceled_at')
                    ->translateLabel()
                    ->required(fn(Forms\Get $get) => $get('status') == SubscriptionStatusEnum::CANCELED->value),
                Forms\Components\Textarea::make('obs')
                    ->translateLabel()
                    ->columnSpanFull()
                    ->rows(6),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('service')
                    ->translateLabel()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->translateLabel()
                    ->searchable()
                    ->icon(fn($state) => SubscriptionStatusEnum::from($state)->getIcon())
                    ->color(fn($state) => SubscriptionStatusEnum::from($state)->getColor())
                    ->tooltip(fn($state) => SubscriptionStatusEnum::from($state)->getLabel()),
                Tables\Columns\TextColumn::make('price')
                    ->translateLabel()
                    ->money()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->using(fn(\Illuminate\Database\Query\Builder $query) => $query->sum('price') / 100)
                            ->money(),
                    ]),
                Tables\Columns\TextColumn::make('currency')
                    ->translateLabel()
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->translateLabel()
                    ->wrapHeader()
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_info')
                    ->label('Payment extra-info')
                    ->translateLabel()
                    ->wrapHeader()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('billing_period')
                    ->translateLabel()
                    ->wrapHeader()
                    ->searchable(),
                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->translateLabel()
                    ->wrapHeader()
                    ->date(PejotaHelper::getUserDateFormat())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('canceled_at')
                    ->translateLabel()
                    ->wrapHeader()
                    ->date(PejotaHelper::getUserDateFormat())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->defaultGroup(
                Tables\Grouping\Group::make('billing_period')
                    ->label(__('Billing period'))
                    ->getTitleFromRecordUsing(fn(Model $record) => SubscriptionBillingPeriodEnum::from($record->billing_period)->getLabel())
            )
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
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'view' => Pages\ViewSubscription::route('/{record}'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
