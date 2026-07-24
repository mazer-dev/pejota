<?php

namespace App\Filament\App\Resources;

use App\Enums\FeatureEnum;
use App\Enums\MenuGroupsEnum;
use App\Enums\SubscriptionBillingPeriodEnum;
use App\Enums\SubscriptionStatusEnum;
use App\Filament\App\Concerns\GatesAccessByFeature;
use App\Filament\App\Resources\SubscriptionResource\Pages\CreateSubscription;
use App\Filament\App\Resources\SubscriptionResource\Pages\EditSubscription;
use App\Filament\App\Resources\SubscriptionResource\Pages\ListSubscriptions;
use App\Filament\App\Resources\SubscriptionResource\Pages\ViewSubscription;
use App\Helpers\PejotaHelper;
use App\Models\Subscription;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class SubscriptionResource extends Resource
{
    use GatesAccessByFeature;

    protected static ?string $model = Subscription::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tv';

    public static function feature(): FeatureEnum
    {
        return FeatureEnum::DomainSubscriptions;
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::FINANCE->value);
    }

    public static function getModelLabel(): string
    {
        return __('Subscription');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make([
                    'default' => 2,
                ])->schema([
                    TextInput::make('service')
                        ->columnSpan(2)
                        ->translateLabel()
                        ->required(),
                    TextInput::make('price')
                        ->translateLabel()
                        ->required()
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make('currency')
                        ->translateLabel()
                        ->required(),
                    TextInput::make('payment_method')
                        ->translateLabel()
                        ->required(),
                    TextInput::make('payment_info')
                        ->label('Payment extra-info')
                        ->translateLabel(),
                    Select::make('status')
                        ->options(SubscriptionStatusEnum::class)
                        ->translateLabel()
                        ->required(),
                    Select::make('billing_period')
                        ->options(SubscriptionBillingPeriodEnum::class)
                        ->translateLabel()
                        ->required(),
                    DatePicker::make('trial_ends_at')
                        ->translateLabel()
                        ->required(fn (Get $get) => $get('status') == SubscriptionStatusEnum::TRIAL->value),
                    DatePicker::make('canceled_at')
                        ->translateLabel()
                        ->required(fn (Get $get) => $get('status') == SubscriptionStatusEnum::CANCELED->value),
                    Textarea::make('obs')
                        ->translateLabel()
                        ->columnSpanFull()
                        ->rows(6),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('service')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('status')
                    ->translateLabel()
                    ->searchable()
                    ->icon(fn ($state) => SubscriptionStatusEnum::from($state)->getIcon())
                    ->color(fn ($state) => SubscriptionStatusEnum::from($state)->getColor())
                    ->tooltip(fn ($state) => SubscriptionStatusEnum::from($state)->getLabel()),
                TextColumn::make('price')
                    ->translateLabel()
                    ->money()
                    ->sortable()
                    ->summarize([
                        Summarizer::make()
                            ->using(fn (Builder $query) => $query->sum('price') / 100)
                            ->money(),
                    ]),
                TextColumn::make('currency')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->translateLabel()
                    ->wrapHeader()
                    ->searchable(),
                TextColumn::make('payment_info')
                    ->label('Payment extra-info')
                    ->translateLabel()
                    ->wrapHeader()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('billing_period')
                    ->translateLabel()
                    ->wrapHeader()
                    ->searchable(),
                TextColumn::make('trial_ends_at')
                    ->translateLabel()
                    ->wrapHeader()
                    ->date(PejotaHelper::getUserDateFormat())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('canceled_at')
                    ->translateLabel()
                    ->wrapHeader()
                    ->date(PejotaHelper::getUserDateFormat())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->defaultGroup(
                Group::make('billing_period')
                    ->label(__('Billing period'))
                    ->getTitleFromRecordUsing(
                        fn (Model $record) => SubscriptionBillingPeriodEnum::from($record->billing_period)->getLabel()
                    )
            )
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
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
            'index' => ListSubscriptions::route('/'),
            'create' => CreateSubscription::route('/create'),
            'view' => ViewSubscription::route('/{record}'),
            'edit' => EditSubscription::route('/{record}/edit'),
        ];
    }
}
