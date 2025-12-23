<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DailyLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'dailyLogs';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                /* Input for the log date */
            Forms\Components\DatePicker::make('log_date')
                ->required()
                ->default(now()),

            /* Textarea for log details and activities */
            Forms\Components\Textarea::make('description')
                ->required()
                ->columnSpanFull(),

            /* Multi-image upload for site photos */
            Forms\Components\FileUpload::make('photos')
                ->multiple()
                ->image()
                ->directory('daily-logs')
                ->columnSpanFull(),
            ]);
    }

   public function table(Table $table): Table
{
    return $table
        /* Set the title attribute for the record, usually for searching/linking */
        ->recordTitleAttribute('log_date') 
        ->columns([
            /* Display the date of the daily log with sorting capability */
            Tables\Columns\TextColumn::make('log_date')
                ->label('Log Date')
                ->date()
                ->sortable(),

            /* Show a preview of the description, limited to 50 characters */
            Tables\Columns\TextColumn::make('description')
                ->label('Description')
                ->limit(50)
                ->searchable(),

            /* Display a stack of photos uploaded in the log */
            Tables\Columns\ImageColumn::make('photos')
                ->label('Photos')
                ->circular()
                ->stacked(),
        ])
        ->filters([
            // Define table filters here if needed
        ])
        ->headerActions([
            /* Action to create a new daily log directly from the project view */
            Tables\Actions\CreateAction::make(),
        ])
        ->actions([
            /* Standard actions to edit or delete a specific log entry */
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                /* Allow deleting multiple logs at once */
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
}
}
