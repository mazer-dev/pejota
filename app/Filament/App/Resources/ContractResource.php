<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Resources\ContractResource\Pages;
use App\Filament\App\Resources\ContractResource\RelationManagers;
use App\Helpers\PejotaHelper;
use App\Models\Contract;
use Carbon\Carbon;
use Faker\Provider\Text;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = MenuSortEnum::CONTRACTS->value;

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::ADMINISTRATION->value);
    }

    public static function getModelLabel(): string
    {
        return __('Contract');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Grid::make(3)->schema([
                    Select::make('with')
                        ->translateLabel()
                        ->options([
                            'client' => __('Client'),
                            'vendor' => __('Vendor'),
                        ])
                        ->default('client')
                        ->live()
                        ->afterStateUpdated(function ($state, $get, $set) {
                            if ($state === 'client') {
                                return $set('vendor_id', null);
                            }

                            return $set('client_id', null);
                        })
                        ->required(),
                    Select::make('client_id')
                        ->translateLabel()
                        ->relationship('client', 'name')
                        ->preload()
                        ->searchable()
                        ->visible(fn($get) => $get('with') === 'client')
                        ->required(fn($get) => $get('with') === 'client'),
                    Select::make('vendor_id')
                        ->translateLabel()
                        ->relationship('vendor', 'name')
                        ->preload()
                        ->searchable()
                        ->visible(fn($get) => $get('with') === 'vendor')
                        ->required(fn($get) => $get('with') === 'vendor'),
                    Select::make('project_id')
                        ->label('Project')
                        ->translateLabel()
                        ->relationship(
                            'project',
                            'name',
                            fn(Builder $query, Forms\Get $get) => $query->byClient($get('client'))->orderBy('name')
                        )
                        ->searchable()
                        ->preload()
                        ->required(),

                ]),
                TextInput::make('title')
                    ->translateLabel()
                    ->required(),
                Grid::make(3)->schema([
                    DatePicker::make('start_at')
                        ->translateLabel()
                        ->required(),
                    DatePicker::make('end_at')
                        ->translateLabel(),
                    TextInput::make('total')
                        ->translateLabel()
                        ->prefixIcon('heroicon-o-currency-dollar')
                        ->required()
                        ->numeric(),
                ]),
                RichEditor::make('content')
                    ->translateLabel()
                    ->required(),
                TableRepeater::make('signatures')
                    ->label(__('Signatures'))
                    ->schema([
                        TextInput::make('name')
                            ->translateLabel()
                            ->required(),
                        Select::make('role')
                            ->translateLabel()
                            ->options([
                                'client' => __('Client'),
                                'vendor' => __('Vendor'),
                                'witness' => __('Witness'),
                            ])
                            ->required(),
                        DatePicker::make('date')
                            ->translateLabel()
                            ->required()
                    ])
                    ->addActionLabel(__('Add item'))
                    ->defaultItems(0)
                    ->colStyles([
                        'item' => 'width:70%'
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                'client.name',
                'project.name',
                'start_at',
            ])
            ->striped()
            ->columns([
                TextColumn::make('title')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('with')
                    ->translateLabel()
                    ->getStateUsing((fn(Contract $record): string => $record->client_id ? __('Client') : __('Vendor'))),
                TextColumn::make('who')
                    ->translateLabel()
                    ->getStateUsing(
                        (fn(Contract $record
                        ): string => $record->client_id ? $record->client->name : $record->vendor->name)
                    ),
                TextColumn::make('project.name')
                    ->translateLabel(),
                TextColumn::make('start_at')
                    ->translateLabel()
                    ->date(PejotaHelper::getUserDateFormat()),
                TextColumn::make('end_at')
                    ->translateLabel()
                    ->date(PejotaHelper::getUserDateFormat()),
                TextColumn::make('total')
                    ->translateLabel()
                    ->money(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->translateLabel()
                    ->relationship('client', 'name'),
                Tables\Filters\SelectFilter::make('vendor')
                    ->translateLabel()
                    ->relationship('vendor', 'name'),
                Tables\Filters\Filter::make('end_at_not_empty')
                    ->translateLabel()
                    ->form([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\ToggleButtons::make('end_at')
                                ->translateLabel()
                                ->options([
                                    'not_empty' => __('Has end date'),
                                    'empty' => __('No end date'),
                                ]),
                        ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['end_at'] == 'not_empty') {
                            return $query->whereNotNull('end_at');
                        } else {
                            if ($data['end_at'] == 'empty') {
                                return $query->whereNull('end_at');
                            }
                        }
                        return $query;
                    }),
                Tables\Filters\Filter::make('end_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->translateLabel(),
                        Forms\Components\DatePicker::make('to')
                            ->translateLabel(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->where('start_at', '>=', $data['from'])
                            )
                            ->when(
                                $data['to'],
                                fn(Builder $query, $date): Builder => $query->where('start_at', '<=', $data['to'])
                            );
                    }),
            ], layout: Tables\Enums\FiltersLayout::Modal)
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
            ])
            ->persistFiltersInSession();
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
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
        ];
    }
}
