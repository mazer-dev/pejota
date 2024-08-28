<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Enums\SubscriptionBillingPeriodEnum;
use App\Enums\SubscriptionStatusEnum;
use App\Filament\App\Resources\SubscriptionResource\Pages;
use App\Filament\App\Resources\SubscriptionResource\RelationManagers;
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
                    ->required(fn (Forms\Get $get) => $get('status') == SubscriptionStatusEnum::TRIAL->value),
                Forms\Components\DatePicker::make('canceled_at')
                    ->translateLabel()
                    ->required(fn (Forms\Get $get) => $get('status') == SubscriptionStatusEnum::CANCELED->value),
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
                Tables\Columns\TextColumn::make('company_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('service')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_info')
                    ->searchable(),
                Tables\Columns\TextColumn::make('canceled_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('billing_period')
                    ->searchable(),
                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
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
