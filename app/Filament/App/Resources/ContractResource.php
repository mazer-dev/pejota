<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ContractResource\Pages;
use App\Filament\App\Resources\ContractResource\RelationManagers;
use App\Models\Contract;
use Carbon\Carbon;
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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Grid::make(2)->schema([
                    Select::make('client_id')
                    ->relationship('client', 'name')
                    ->preload()->searchable()->required(),
                    Select::make('project_id')
                    ->label('Project')
                    ->relationship(
                        'project',
                        'name',
                        fn(Builder $query, Forms\Get $get) => $query->byClient($get('client'))->orderBy('name')
                    )
                    ->searchable()->preload()->required(),

                ]),
                TextInput::make('title')
                ->required(),
                Grid::make(2)->schema([
                    DatePicker::make('start_at')->required(),
                    DatePicker::make('end_at'),
                ]),
                RichEditor::make('content')->required(),
                Grid::make(3)
                ->schema([
                    TableRepeater::make('signatures')
                    ->schema([
                        TextInput::make('name')->required(),
                        Select::make('role')
                        ->options([
                            'client',
                            'vendor',
                            'witness'
                        ])->required(),
                        DatePicker::make('date')->required()
                    ])
                    ->addActionLabel('add item')
                    ->defaultItems(0)
                    ->colStyles([
                        'item' => 'width:70%'
                    ]),
                ])
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
            ->columns([
                TextColumn::make('title')
                ->searchable(),
                TextColumn::make('client.name'),
                TextColumn::make('project.name'),
                TextColumn::make('start_at')
                ->formatStateUsing(fn(Model $record) => Carbon::parse($record->start_at)->format('d/m/Y'))
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'name'),
                Tables\Filters\Filter::make('end_at_not_empty')
                    ->form([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\ToggleButtons::make('end_at')
                                ->options([
                                    'not_empty' => 'Has end date',
                                    'empty' => 'No end date',
                                ]),
                        ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['end_at'] == 'not_empty') {
                            return $query->whereNotNull('end_at');
                        } else if ($data['end_at'] == 'empty') {
                            return $query->whereNull('end_at');
                        }                     
                        return $query;
                    }),
                Tables\Filters\Filter::make('end_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('to'),
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
