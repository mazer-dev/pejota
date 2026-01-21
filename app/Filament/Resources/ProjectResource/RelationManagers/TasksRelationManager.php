<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

  public function form(Form $form): Form
{
    return $form
        ->schema([
            /* The title or name of the specific task */
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255),

            /* Detailed instructions or notes for the task */
            Forms\Components\Textarea::make('description')
                ->columnSpanFull(),

            /* The current status of the task (Pending, Ongoing, Done) */
            Forms\Components\Select::make('task_status_id')
                ->relationship('status', 'name')
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\Hidden::make('status_id')
                ->default(1),
        ]);
}

public function table(Table $table): Table
{
    return $table
        ->recordTitleAttribute('title')
        ->columns([
            /* Display the task title with search support */
            Tables\Columns\TextColumn::make('title')
                ->searchable()
                ->sortable(),

            /* Display the status with specific colors for better UI */
            Tables\Columns\TextColumn::make('status.name')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'Done' => 'success',
                    'Pending' => 'warning',
                    'In Progress' => 'primary',
                    default => 'gray',
                }),
        ])
        ->headerActions([
            Tables\Actions\CreateAction::make(),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
}
}
