<?php

namespace App\Filament\App\Resources;

use App\Enums\PriorityEnum;
use App\Filament\App\Resources\TaskResource\Pages;
use App\Filament\App\Resources\TaskResource\RelationManagers;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                        ->preload(),
                    Forms\Components\Select::make('project')
                        ->relationship('project', 'name')
                        ->preload(),
                    Forms\Components\Select::make('parent_task')
                        ->relationship('parent', 'title')
                        ->searchable(),
                ]),
                Forms\Components\TextInput::make('title')
                    ->columnSpanFull()
                    ->required(),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Textarea::make('description')
                        ->columnSpanFull(),
                    Forms\Components\Select::make('priority')
                        ->options(PriorityEnum::class)
                        ->default(PriorityEnum::MEDIUM)
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->required()
                        ->relationship('status', 'name'),
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
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('priority')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('parent_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('task_status_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('planned_start')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('planned_end')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('actual_start')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('actual_end')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('company_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('project_id')
                    ->numeric()
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
                //
            ])
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
