<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyLogResource\Pages;
use App\Models\DailyLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DailyLogResource extends Resource
{
    protected static ?string $model = DailyLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // 1. Select the project (Linked to Project model)
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->required()
                    ->searchable(),

                // 2. Select the log date (Defaults to today)
                Forms\Components\DatePicker::make('log_date')
                    ->default(now())
                    ->required(),

                // 3. Detailed work description
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),

                // 4. Photo upload section
                Forms\Components\FileUpload::make('photos')
                    ->multiple()
                    ->image()
                    ->directory('daily-logs')
                    ->columnSpanFull(),

                // 5. Materials used repeater
                Forms\Components\Repeater::make('materials')
                    ->relationship('materials')
                    ->schema([
                        Forms\Components\Select::make('material_id')
                            ->label('Material')
                            ->relationship('materials', 'name')
                            ->required()
                            ->searchable(),
                        
                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('log_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
                Tables\Columns\ImageColumn::make('photos')
                    ->circular()
                    ->stacked(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailyLogs::route('/'),
            'create' => Pages\CreateDailyLog::route('/create'),
            'edit' => Pages\EditDailyLog::route('/{record}/edit'),
        ];
    }
}