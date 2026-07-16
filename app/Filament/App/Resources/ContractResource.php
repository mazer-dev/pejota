<?php

namespace App\Filament\App\Resources;

use App\Enums\FeatureEnum;
use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Concerns\GatesAccessByFeature;
use App\Filament\App\Resources\ContractResource\Pages\CreateContract;
use App\Filament\App\Resources\ContractResource\Pages\EditContract;
use App\Filament\App\Resources\ContractResource\Pages\ListContracts;
use App\Helpers\PejotaHelper;
use App\Models\Contract;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractResource extends Resource
{
    use GatesAccessByFeature;

    protected static ?string $model = Contract::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = MenuSortEnum::CONTRACTS->value;

    public static function feature(): FeatureEnum
    {
        return FeatureEnum::Contracts;
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::ADMINISTRATION->value);
    }

    public static function getModelLabel(): string
    {
        return __('Contract');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
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
                        ->visible(fn ($get) => $get('with') === 'client')
                        ->required(fn ($get) => $get('with') === 'client'),
                    Select::make('vendor_id')
                        ->translateLabel()
                        ->relationship('vendor', 'name')
                        ->preload()
                        ->searchable()
                        ->visible(fn ($get) => $get('with') === 'vendor')
                        ->required(fn ($get) => $get('with') === 'vendor'),
                    Select::make('project_id')
                        ->label('Project')
                        ->translateLabel()
                        ->relationship(
                            'project',
                            'name',
                            fn (Builder $query, Get $get) => $query->byClient($get('client'))->orderBy('name')
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
                Repeater::make('signatures')
                    ->label(__('Signatures'))
                    ->table([
                        TableColumn::make(__('Name')),
                        TableColumn::make(__('Role')),
                        TableColumn::make(__('Date')),
                    ])
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
                            ->required(),
                    ])
                    ->addActionLabel(__('Add item'))
                    ->defaultItems(0),
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
                    ->getStateUsing((fn (Contract $record): string => $record->client_id ? __('Client') : __('Vendor'))),
                TextColumn::make('who')
                    ->translateLabel()
                    ->getStateUsing(
                        (fn (Contract $record
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
                SelectFilter::make('client')
                    ->translateLabel()
                    ->relationship('client', 'name'),
                SelectFilter::make('vendor')
                    ->translateLabel()
                    ->relationship('vendor', 'name'),
                Filter::make('end_at_not_empty')
                    ->translateLabel()
                    ->schema([
                        Grid::make(2)->schema([
                            ToggleButtons::make('end_at')
                                ->translateLabel()
                                ->options([
                                    'not_empty' => __('Has end date'),
                                    'empty' => __('No end date'),
                                ]),
                        ]),
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
                Filter::make('end_at')
                    ->schema([
                        DatePicker::make('from')
                            ->translateLabel(),
                        DatePicker::make('to')
                            ->translateLabel(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->where('start_at', '>=', $data['from'])
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->where('start_at', '<=', $data['to'])
                            );
                    }),
            ], layout: FiltersLayout::Modal)
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
            'index' => ListContracts::route('/'),
            'create' => CreateContract::route('/create'),
            'edit' => EditContract::route('/{record}/edit'),
        ];
    }
}
