<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\WorkSessionResource\Pages;
use App\Filament\App\Resources\WorkSessionResource\RelationManagers;
use App\Helpers\PejotaHelper;
use App\Models\WorkSession;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use NunoMaduro\Collision\Adapters\Phpunit\State;

class WorkSessionResource extends Resource
{
    protected static ?string $model = WorkSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Forms\Components\TextInput::make('title'),

                Forms\Components\Split::make([
                    Forms\Components\Section::make()->schema([

                        Forms\Components\RichEditor::make('description')
                            ->label('')
                            ->fileAttachmentsDisk('work_sessions')
                            ->fileAttachmentsDirectory(auth()->user()->company->id)
                            ->fileAttachmentsVisibility('private'),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('client')
                                ->relationship('client', 'name')
                                ->preload(),
                            Forms\Components\Select::make('project')
                                ->relationship('project', 'name')
                                ->preload(),
                        ]),
                    ]),

                    Forms\Components\Section::make('')->schema([

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\DateTimePicker::make('start')
                                ->timezone(PejotaHelper::getUserTimeZone())
                                ->required()
                                ->default(fn(): string => now()->toDateTimeString())
                                ->live(),

                            Forms\Components\DateTimePicker::make('end')
                                ->timezone(PejotaHelper::getUserTimeZone())
                                ->required()
                                ->live()
                                ->afterStateUpdated(
                                    fn(
                                        Forms\Get $get,
                                        Forms\Set $set): mixed => self::setTimers(
                                        fromDuration: false,
                                        get: $get,
                                        set: $set
                                    )
                                ),
                        ]),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('duration')
                                ->required()
                                ->numeric()
                                ->integer()
                                ->default(0)
                                ->helperText('Duration in minutes. If you enter manually end time, it will be calculated.')
                                ->live()
                                ->afterStateUpdated(
                                    fn(
                                        Forms\Get $get,
                                        Forms\Set $set): mixed => self::setTimers(
                                        fromDuration: true,
                                        get: $get,
                                        set: $set
                                    )
                                ),


                            Forms\Components\TextInput::make('rate')
                                ->required()
                                ->numeric()
                                ->default(0),

                            Forms\Components\TextInput::make('time')
                                ->label('Session time')
                                ->disabled(),
                        ]),

                        Forms\Components\Select::make('task')
                            ->relationship('task', 'title')
                            ->searchable(),

                    ]),

                ]),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('duration')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start')
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable(),
                Tables\Columns\TextColumn::make('end')
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->searchable(),
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
                Tables\Columns\TextColumn::make('task_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListWorkSessions::route('/'),
            'create' => Pages\CreateWorkSession::route('/create'),
            'view' => Pages\ViewWorkSession::route('/{record}'),
            'edit' => Pages\EditWorkSession::route('/{record}/edit'),
        ];
    }

    public static function setTimers(bool $fromDuration, Forms\Get $get, Forms\Set $set)
    {
        $start = $get('start');
        $end = $get('end');
        $duration = (int)$get('duration');

        if (!$end && !$duration) {
            return;
        }

        $start = CarbonImmutable::parse($start);

        if ($fromDuration) {
            $end = $start->addMinutes($duration);
        }

        $end = Carbon::parse($end);
        $set('end', $end->toDateTimeString());

        $duration = (int)$start->diffInMinutes($end);

        $set('duration', $duration);

        $set('time', PejotaHelper::formatDuration((int)$get('duration')));
    }
}
