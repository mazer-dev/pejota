<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
    ->required()
    ->unique(ignoreRecord: true),
Forms\Components\TextInput::make('name')
    ->required(),
Forms\Components\Select::make('client_id')
    ->relationship('client', 'name')
    ->searchable()
    ->preload(),
Forms\Components\TextInput::make('budget')
    ->numeric()
    ->prefix('$'),
Forms\Components\Select::make('status')
    ->options([
        'active' => 'Active',
        'completed' => 'Completed',
        'on-hold' => 'On Hold',
    ])->default('active'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
              Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('client.name')->label('Client'),
            Tables\Columns\TextColumn::make('budget')->money('usd'),
            Tables\Columns\BadgeColumn::make('status')
            ->colors([
                    'warning' => 'active',
                    'success' => 'completed',
                    'danger' => 'on-hold',
                ])
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
            // Link Daily Logs to the Project view to show them as a sub-section
           RelationManagers\DailyLogsRelationManager::class,
           /* Link Tasks to the Project to manage specific work items */
           RelationManagers\TasksRelationManager::class,
           RelationManagers\MilestonesRelationManager::class , 
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
            
        ];
    }
}
