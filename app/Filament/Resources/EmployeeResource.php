<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

   public static function form(Form $form): Form
{
    return $form
        ->schema([
            // Employee Basic Info Section
            Forms\Components\Section::make('Personal Information')
                ->description('Enter the employee full name and contact details.')
                ->schema([
                    Forms\Components\TextInput::make('full_name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->required(),
                ])->columns(2),

            // Job Details Section
            Forms\Components\Section::make('Employment Details')
                ->schema([
                    Forms\Components\TextInput::make('job_title')
                        ->required(),
                    Forms\Components\TextInput::make('salary')
                        ->numeric()
                        ->prefix('$')
                        ->required(),
                    Forms\Components\DatePicker::make('hire_date')
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'on_leave' => 'On Leave',
                        ])->default('active')
                        ->required(),
                ])->columns(2),
        ]);
}
   public static function table(Table $table): Table
{
    return $table
        ->columns([
            // Displaying the basic info in the list
            Tables\Columns\TextColumn::make('full_name')
                ->searchable() // Allows searching by name
                ->sortable(),  // Allows sorting A-Z
            
            Tables\Columns\TextColumn::make('job_title')
                ->label('Position')
                ->badge() // Makes it look like a nice tag
                ->color('info'),
                
            Tables\Columns\TextColumn::make('salary')
                ->money('USD') // Formats as $1,000.00
                ->sortable(),

            Tables\Columns\TextColumn::make('hire_date')
                ->date() // Formats as Jan 2, 2026
                ->sortable(),

            Tables\Columns\SelectColumn::make('status')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                    'on_leave' => 'On Leave',
                ]),
        ])
        ->filters([
            // Add a filter to see only Active or Inactive employees
            Tables\Filters\SelectFilter::make('status')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                    'on_leave' => 'On Leave',
                ]),
        ])
        ->actions([
            Tables\Actions\ViewAction::make(), // The 4th page: Details view
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
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'view' => Pages\ViewEmployee::route('/{record}'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
