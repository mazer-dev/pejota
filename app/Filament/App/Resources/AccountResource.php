<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Resources\AccountResource\Pages\CreateAccount;
use App\Filament\App\Resources\AccountResource\Pages\EditAccount;
use App\Filament\App\Resources\AccountResource\Pages\ListAccounts;
use App\Filament\App\Resources\AccountResource\Pages\ViewAccount;
use App\Helpers\PejotaHelper;
use App\Models\Account;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = MenuSortEnum::ACCOUNTS->value;

    public static function getModelLabel(): string
    {
        return __('Account');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::FINANCE->value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->translateLabel()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('description')
                    ->translateLabel()
                    ->columnSpanFull(),
                TextInput::make('initial_balance')
                    ->translateLabel()
                    ->required()
                    ->numeric()
                    ->default(0),
                DatePicker::make('initial_balance_at')
                    ->translateLabel()
                    ->default(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('description')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('initial_balance')
                    ->translateLabel()
                    ->money()
                    ->sortable(),
                TextColumn::make('initial_balance_at')
                    ->translateLabel()
                    ->date(
                        PejotaHelper::getUserDateFormat()
                    )
                    ->sortable(),
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
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'view' => ViewAccount::route('/{record}'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }
}
