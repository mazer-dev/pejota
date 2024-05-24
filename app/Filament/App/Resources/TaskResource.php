<?php

namespace App\Filament\App\Resources;

use App\Enums\PriorityEnum;
use App\Filament\App\Resources\TaskResource\Pages;
use App\Filament\App\Resources\TaskResource\RelationManagers;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('client')
                        ->relationship('client', 'name')
                        ->preload()
                        ->live(),
                    Forms\Components\Select::make('project_id')
                        ->label('Project')
                        ->options(function (Forms\Get $get): Collection {
                            $query = Project::orderBy('name');
                            if ($get('client') != null) {
                                $query->where('client_id', $get('client'));
                            }

                            return $query->pluck('name', 'id');
                        }),
                    Forms\Components\Select::make('parent_task')
                        ->relationship('parent', 'title')
                        ->searchable(),
                ]),
                Forms\Components\TextInput::make('title')
                    ->columnSpanFull()
                    ->required(),

                Forms\Components\Section::make('Description')
                    ->collapsible()
                    ->compact()
                    ->label('Descritpion')
                    ->schema([
                        Forms\Components\RichEditor::make('description')
                            ->label('')
                            ->columnSpanFull()
                            ->extraInputAttributes(
                                ['style' => 'max-height: 300px; overflow: scroll']),

                    ]),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('priority')
                        ->options(PriorityEnum::class)
                        ->default(PriorityEnum::MEDIUM)
                        ->required(),
                    Forms\Components\Select::make('status_id')
                        ->required()
                        ->options(
                            Status::orderBy('sort_order')->pluck('name', 'id')
                        )
                        ->default(Status::orderBy('sort_order')->first()->id),

                    Forms\Components\DatePicker::make('due_date'),
                ]),
                Forms\Components\DatePicker::make('planned_start'),
                Forms\Components\DatePicker::make('planned_end'),
                Forms\Components\DatePicker::make('actual_start'),
                Forms\Components\DatePicker::make('actual_end'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                'client.name',
                'project.name',
                'due_date',
                'status.name',
            ])
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Medium)
                    ->searchable(),
                Tables\Columns\SelectColumn::make('priority')
                    ->options(PriorityEnum::class)
                    ->sortable(),
                Tables\Columns\SelectColumn::make('status_id')
                    ->label('Status')
                    ->options(fn(): array => Status::all()->pluck('name', 'id')->toArray())
                    ->searchable()->sortable(),
                Tables\Columns\ColorColumn::make('status.color')
                    ->label(''),
                Tables\Columns\TextColumn::make('planned_start')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('planned_end')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->sortable(),
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
                Tables\Filters\SelectFilter::make('status')
                    ->relationship('status', 'name')
                    ->multiple(true)
                    ->preload(),
                Tables\Filters\Filter::make('due_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('to')
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->where('due_date', '>=', $data['from'])
                            )
                            ->when(
                                $data['to'],
                                fn(Builder $query, $date): Builder => $query->where('due_date', '<=', $data['to'])
                            );
                    })
            ], layout: Tables\Enums\FiltersLayout::Modal)
            ->persistFiltersInSession()
            ->actions([
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
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}
