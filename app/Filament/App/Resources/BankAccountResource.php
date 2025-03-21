<?php

namespace App\Filament\App\Resources;

use App\Enums\BankAccountType;
use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Resources\BankAccountResource\Pages;
use App\Helpers\PejotaHelper;
use App\Models\BankAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

//    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = MenuSortEnum::ACCOUNTS->value;

    public static function getModelLabel(): string
    {
        return __('Account');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Accounts');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::FINANCE->value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->translateLabel()
                            ->default(true)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('name')
                            ->translateLabel()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->translateLabel()
                            ->required()
                            ->options(
                                collect(BankAccountType::cases())->mapWithKeys(
                                    fn($type) => [$type->value => $type->label()]
                                )
                            )
                            ->live()
                            ->default(BankAccountType::CHECKING->value),

                        Forms\Components\TextInput::make('bank_name')
                            ->translateLabel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('account_number')
                            ->translateLabel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('agency')
                            ->translateLabel()
                            ->maxLength(255),

                        Forms\Components\Select::make('currency')
                            ->translateLabel()
                            ->required()
                            ->options([
                                'BRL' => 'BRL',
                                'USD' => 'USD',
                                'EUR' => 'EUR',
                                'GBP' => 'GBP',
                            ])
                            ->default('BRL'),

                        Forms\Components\TextInput::make('initial_balance')
                            ->translateLabel()
                            ->required()
                            ->numeric()
                            ->default(0),

                        Forms\Components\DatePicker::make('initial_balance_date')
                            ->translateLabel()
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('current_balance')
                            ->translateLabel()
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Forms\Components\Section::make(__('Credit Card'))
                    ->schema([
                        Forms\Components\TextInput::make('credit_limit')
                            ->translateLabel()
                            ->numeric(),

                        Forms\Components\TextInput::make('due_day')
                            ->translateLabel()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31),

                        Forms\Components\TextInput::make('closing_day')
                            ->translateLabel()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31),
                    ])
                    ->columns(3)
                    ->visible(fn(Forms\Get $get) => $get('type') === BankAccountType::CREDIT_CARD->value),

                Forms\Components\Section::make(__('Loan'))
                    ->schema([
                        Forms\Components\TextInput::make('loan_amount')
                            ->translateLabel()
                            ->numeric(),

                        Forms\Components\TextInput::make('interest_rate')
                            ->translateLabel()
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\DatePicker::make('loan_start_date')
                            ->translateLabel(),

                        Forms\Components\DatePicker::make('loan_end_date')
                            ->translateLabel(),
                    ])
                    ->columns(2)
                    ->visible(fn(Forms\Get $get) => $get('type') === BankAccountType::LOAN->value),

                Forms\Components\Textarea::make('notes')
                    ->translateLabel()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\IconColumn::make('active')
                    ->translateLabel()
                    ->boolean(),

                Tables\Columns\TextColumn::make('name')
                    ->translateLabel()
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->translateLabel()
                    ->formatStateUsing(fn(BankAccountType $state): string => $state->label()),

                Tables\Columns\TextColumn::make('bank_name')
                    ->translateLabel()
                    ->searchable(),

                Tables\Columns\TextColumn::make('account_number')
                    ->translateLabel()
                    ->searchable(),

                Tables\Columns\TextColumn::make('current_balance')
                    ->translateLabel()
                    ->money(fn(Model $record): string => $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('currency')
                    ->translateLabel(),

                Tables\Columns\TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->translateLabel()
                    ->options(
                        collect(BankAccountType::cases())->mapWithKeys(fn($type) => [$type->value => $type->label()])
                    ),

                Tables\Filters\TernaryFilter::make('active')
                    ->translateLabel(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Split::make([
                    Grid::make(1)
                        ->schema([
                            Section::make([
                                TextEntry::make('name')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->hiddenLabel(),

                                TextEntry::make('type')
                                    ->translateLabel()
                                    ->formatStateUsing(fn(BankAccountType $state): string => $state->label()),

                                TextEntry::make('bank_name')
                                    ->translateLabel(),

                                TextEntry::make('account_number')
                                    ->translateLabel(),

                                TextEntry::make('agency')
                                    ->translateLabel(),

                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('initial_balance')
                                            ->translateLabel()
                                            ->money(fn(Model $record): string => $record->currency),

                                        TextEntry::make('initial_balance_date')
                                            ->translateLabel()
                                            ->date(PejotaHelper::getUserDateFormat()),

                                        TextEntry::make('current_balance')
                                            ->translateLabel()
                                            ->money(fn(Model $record): string => $record->currency),

                                        TextEntry::make('currency')
                                            ->translateLabel(),
                                    ]),

                                TextEntry::make('active')
                                    ->translateLabel()
                                    ->badge(),
                            ]),

                            Section::make(__('Credit Card'))
                                ->schema([
                                    TextEntry::make('credit_limit')
                                        ->translateLabel()
                                        ->money(fn(Model $record): string => $record->currency),

                                    TextEntry::make('due_day')
                                        ->translateLabel(),

                                    TextEntry::make('closing_day')
                                        ->translateLabel(),
                                ])
                                ->visible(fn(Model $record) => $record->type === BankAccountType::CREDIT_CARD),

                            Section::make(__('Loan'))
                                ->schema([
                                    TextEntry::make('loan_amount')
                                        ->translateLabel()
                                        ->money(fn(Model $record): string => $record->currency),

                                    TextEntry::make('interest_rate')
                                        ->translateLabel()
                                        ->suffix('%'),

                                    TextEntry::make('loan_start_date')
                                        ->translateLabel()
                                        ->date(PejotaHelper::getUserDateFormat()),

                                    TextEntry::make('loan_end_date')
                                        ->translateLabel()
                                        ->date(PejotaHelper::getUserDateFormat()),
                                ])
                                ->visible(fn(Model $record) => $record->type === BankAccountType::LOAN),

                            Section::make(__('Notes'))
                                ->schema([
                                    TextEntry::make('notes')
                                        ->translateLabel(),
                                ])
                                ->hidden(fn(Model $record) => empty($record->notes)),

                        ]),

                    Section::make([
                        TextEntry::make('created_at')
                            ->translateLabel()
                            ->dateTime()
                            ->timezone(PejotaHelper::getUserTimeZone()),

                        TextEntry::make('updated_at')
                            ->translateLabel()
                            ->dateTime()
                            ->timezone(PejotaHelper::getUserTimeZone()),

                        Actions::make([
                            Action::make('edit')
                                ->translateLabel()
                                ->url(fn(Model $record) => "{$record->id}/edit")
                                ->icon('heroicon-o-pencil'),

                            Action::make('back')
                                ->translateLabel()
                                ->url(fn(Model $record) => './.')
                                ->icon('heroicon-o-chevron-left')
                                ->color(Color::Neutral),
                        ]),
                    ])->grow(false),
                ])
                    ->columnSpanFull(),
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
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'view' => Pages\ViewBankAccount::route('/{record}'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}