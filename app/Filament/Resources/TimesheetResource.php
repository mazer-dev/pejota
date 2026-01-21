<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TimesheetResource\Pages;
use App\Filament\Resources\TimesheetResource\RelationManagers;
use App\Models\Timesheet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TimesheetResource extends Resource
{
    protected static ?string $model = Timesheet::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

  public static function form(Form $form): Form
{
    return $form
        ->schema([
            // Select the user (worker) for this timesheet entry
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'name')
                ->required()
                ->searchable(),

            // Associate the entry with a specific project
            Forms\Components\Select::make('project_id')
                ->relationship('project', 'name')
                ->required(),

            // Select the specific task within the project
           Forms\Components\Select::make('task_id')
    ->relationship('task', 'title')
    ->required()
    ->createOptionForm([
        Forms\Components\TextInput::make('title')
            ->required(),
        Forms\Components\Select::make('project_id')
            ->relationship('project', 'name')
            ->required(),
        // Add this line to fix the "status_id" error
        Forms\Components\Hidden::make('status_id')
            ->default(1), // Assuming 1 is the ID for a default status like 'To Do'
    ]),
            // The date of work
            Forms\Components\DatePicker::make('date')
                ->required()
                ->default(now()),

            // Total decimal hours worked
            Forms\Components\TextInput::make('hours')
                ->numeric()
                ->required(),
        ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            // Display User, Project, and Task in the list
            Tables\Columns\TextColumn::make('user.name')
                ->sortable()
                ->searchable(),
            
            Tables\Columns\TextColumn::make('project.name')
                ->sortable(),
            
            Tables\Columns\TextColumn::make('date')
                ->date()
                ->sortable(),

            Tables\Columns\TextColumn::make('hours'),

            // The status column for the approval process
            Tables\Columns\BadgeColumn::make('status')
                ->colors([
                    'warning' => 'pending',
                    'success' => 'approved',
                    'danger' => 'rejected',
                ]),
        ])
        ->actions([
            // The "Approve" action satisfies the Approve Page requirement
            Tables\Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                // Button only appears if record isn't approved yet
                ->hidden(fn ($record) => $record->status === 'approved')
                ->action(fn ($record) => $record->update(['status' => 'approved'])),

            Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListTimesheets::route('/'),
            'create' => Pages\CreateTimesheet::route('/create'),
            'edit' => Pages\EditTimesheet::route('/{record}/edit'),
        ];
    }
    
}
