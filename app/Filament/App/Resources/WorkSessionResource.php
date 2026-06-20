<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Resources\ClientResource\Pages\ViewClient;
use App\Filament\App\Resources\ProjectResource\Pages\ViewProject;
use App\Filament\App\Resources\TaskResource\Pages\ViewTask;
use App\Filament\App\Resources\WorkSessionResource\Pages\CreateWorkSession;
use App\Filament\App\Resources\WorkSessionResource\Pages\EditWorkSession;
use App\Filament\App\Resources\WorkSessionResource\Pages\ListWorkSessions;
use App\Filament\App\Resources\WorkSessionResource\Pages\ViewWorkSession;
use App\Helpers\PejotaHelper;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkSession;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class WorkSessionResource extends Resource
{
    protected static ?string $model = WorkSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = MenuSortEnum::WORK_SESSIONS->value;

    public static function getModelLabel(): string
    {
        return __('Work session');
    }

    /**
     * @return string|null
     */
    public static function getPluralModelLabel(): string
    {
        return __('Work sessions');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::DAILY_WORK->value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema(
                self::getFormSchema()
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start', 'desc')
            ->striped()
            ->columns([
                ToggleColumn::make('is_running')
                    ->translateLabel()
                    ->sortable()
                    ->updateStateUsing(function (bool $state, WorkSession $record) {
                        if ($state) {
                            return false;
                        }

                        return self::infolistFinish($record);
                    }),
                TextColumn::make('start')
                    ->label('Started at')
                    ->translateLabel()
                    ->dateTime(PejotaHelper::getUserDateTimeFormat())
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable(),
                TextColumn::make('end')
                    ->label('End at')
                    ->translateLabel()
                    ->dateTime(PejotaHelper::getUserDateTimeFormat())
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->hidden(fn ($livewire) => isset($livewire->activeTab) ? $livewire->activeTab === 'running' : true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('duration')
                    ->label('Time')
                    ->tooltip(
                        fn ($record) => $record?->end?->tz(PejotaHelper::getUserTimeZone())->format(
                            PejotaHelper::getUserDateTimeFormat()
                        )
                    )
                    ->translateLabel()
                    ->formatStateUsing(fn ($state) => PejotaHelper::formatDuration($state))
                    ->hidden(fn ($livewire) => isset($livewire->activeTab) ? $livewire->activeTab === 'running' : true)
                    ->toggleable()
                    ->summarize(
                        Sum::make()
                            ->formatStateUsing(fn ($state) => PejotaHelper::formatDuration($state))
                            ->label('Total time')
                    ),
                TextColumn::make('title')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('value')
                    ->translateLabel()
                    ->money(fn (WorkSession $record): string => $record->currency ?? PejotaHelper::getUserCurrency())
                    ->hidden(fn ($livewire) => isset($livewire->activeTab) ? $livewire->activeTab === 'running' : true)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('Total value')
                            ->formatStateUsing(fn ($state): string => number_format(((float) $state) / 100, 2))
                    ),
                TextColumn::make('currency')
                    ->translateLabel()
                    ->hidden(fn ($livewire) => isset($livewire->activeTab) ? $livewire->activeTab === 'running' : true)
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('billable')
                    ->translateLabel()
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('task.title')
                    ->translateLabel()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('project.name')
                    ->translateLabel()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('client.labelName')
                    ->translateLabel()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime(PejotaHelper::getUserDateTimeFormat())
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime(PejotaHelper::getUserDateTimeFormat())
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('client.name')
                    ->label(__('Client'))
                    ->collapsible(),
                Group::make('project.name')
                    ->label(__('Project'))
                    ->collapsible(),
                Group::make('start')
                    ->label(__('Date'))
                    ->date()
                    ->collapsible(),
            ])
            ->filters([
                SelectFilter::make('client')
                    ->translateLabel()
                    ->relationship('client', 'name'),
                SelectFilter::make('project')
                    ->translateLabel()
                    ->relationship('project', 'name'),
                Filter::make('start')
                    ->form([
                        DateTimePicker::make('from')
                            ->translateLabel(),
                        DateTimePicker::make('to')
                            ->translateLabel(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->where('start', '>=', $data['from'])
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->where('start', '<=', $data['to'])
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['from'] || $data['to']) {
                            return __('Start').': '.$data['from'].' - '.$data['to'];
                        }

                        return null;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Tables\Actions\Action::make(__('Clone'))
                        ->tooltip(__('Clone this session with same time and details, updating to current date'))
                        ->icon('heroicon-o-document-duplicate')
                        ->color(Color::Amber)
                        ->action(fn (WorkSession $record) => self::clone($record)),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make(__('Clone selected'))
                        ->tooltip(__('Clone this session with same time and details, updating to current date'))
                        ->icon('heroicon-o-document-duplicate')
                        ->color(Color::Amber)
                        ->action(fn (Collection $records) => self::cloneCollection($records))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
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
            'index' => ListWorkSessions::route('/'),
            'create' => CreateWorkSession::route('/create'),
            'view' => ViewWorkSession::route('/{record}'),
            'edit' => EditWorkSession::route('/{record}/edit'),
        ];
    }

    public static function getFormSchema(): array
    {
        return [
            TextInput::make('title')
                ->placeholder(__('Title'))
                ->hiddenLabel()
                ->required()
                ->translateLabel(),

            Forms\Components\Grid::make([
                'default' => 2,
                'sm' => 2,
                'md' => 6,
            ])->schema([
                DateTimePicker::make('start')
                    ->label('Start at')
                    ->translateLabel()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->seconds(false)
                    ->required()
                    ->default(fn (): string => now()->toDateTimeString())
                    ->disabled(fn (?WorkSession $record): bool => (bool) $record?->isInvoiced())
                    ->live()
                    ->afterStateUpdated(
                        fn (
                            Get $get,
                            Set $set
                        ): mixed => self::formSetTimers(
                            fromDuration: true,
                            get: $get,
                            set: $set
                        )
                    ),

                DateTimePicker::make('end')
                    ->label('End at')
                    ->translateLabel()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->seconds(false)
                    ->required(fn (Get $get): bool => ! $get('is_running'))
                    ->disabled(fn (?WorkSession $record): bool => (bool) $record?->isInvoiced())
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                            if ($value && $get('start') && Carbon::parse($value)->lessThan(Carbon::parse($get('start')))) {
                                $fail(__('End must be greater than or equal to start.'));
                            }
                        },
                    ])
                    ->live()
                    ->afterStateUpdated(
                        fn (
                            Get $get,
                            Set $set
                        ): mixed => self::formSetTimers(
                            fromDuration: false,
                            get: $get,
                            set: $set
                        )
                    ),

                TextInput::make('duration')
                    ->translateLabel()
                    ->required(fn (Get $get): bool => ! $get('is_running'))
                    ->numeric()
                    ->integer()
                    ->default(0)
                    ->prefixIcon('heroicon-o-play')
                    ->disabled(fn (?WorkSession $record): bool => (bool) $record?->isInvoiced())
                    ->live()
                    ->afterStateUpdated(
                        fn (
                            Get $get,
                            Set $set
                        ): mixed => self::formSetTimers(
                            fromDuration: true,
                            get: $get,
                            set: $set
                        )
                    ),

                TextInput::make('rate')
                    ->translateLabel()
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->disabled(fn (?WorkSession $record): bool => (bool) $record?->isInvoiced()),

                Toggle::make('billable')
                    ->translateLabel()
                    ->inline(false)
                    ->default(true)
                    ->disabled(fn (?WorkSession $record): bool => (bool) $record?->isInvoiced()),

                TextInput::make('currency')
                    ->translateLabel()
                    ->disabled()
                    ->dehydrated()
                    ->default(fn (): string => PejotaHelper::getUserCurrency()),

                Toggle::make('is_running')
                    ->label(fn (bool $state) => $state ? 'Running' : 'Finished')
                    ->onIcon('heroicon-o-stop')
                    ->offIcon('heroicon-o-play')
                    ->offColor('danger')
                    ->translateLabel()
                    ->inline(false)
                    ->default(true)
                    ->live()
                    ->afterStateUpdated(function (bool $state, Get $get, Set $set) {
                        if ($state) {
                            $set('end', null);
                            $set('duration', 0);
                        } else {
                            if (! $get('end')) {
                                $tz = PejotaHelper::getUserTimeZone();
                                $set('end', $tz ? now()->timezone($tz)->format('Y-m-d H:i') : now()->format('Y-m-d H:i'));
                            }
                            self::formSetTimers(false, $get, $set);
                        }
                    }),

                TextInput::make('time')
                    ->translateLabel()
                    ->label('Session time')
                    ->disabled(),
            ]),

            Forms\Components\Grid::make(3)->schema([
                Select::make('client')
                    ->translateLabel()
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm(ClientResource::getSchema())
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set): void {
                        self::fillChildrenFromClient($get, $set);
                        self::applyCascade($get, $set);
                    }),
                Select::make('project')
                    ->label('Project')
                    ->translateLabel()
                    ->relationship(
                        'project',
                        'name',
                        fn (Builder $query, Get $get) => $query->byClient($get('client'))->orderBy('name')
                    )
                    ->searchable()
                    ->preload()
                    ->createOptionForm(ProjectResource::getFormComponents())
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set): void {
                        self::fillClientFromProject($get, $set);
                        self::fillTaskFromProject($get, $set);
                        self::applyCascade($get, $set);
                    }),
                Select::make('task')
                    ->translateLabel()
                    ->relationship('task', 'title')
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set): void {
                        self::fillParentsFromTask($get, $set);
                        self::applyCascade($get, $set);
                    }),

            ]),

            Forms\Components\Section::make(__('Description'))->schema([

                RichEditor::make('description')
                    ->hiddenLabel()
                    ->fileAttachmentsDisk('work_sessions')
                    ->fileAttachmentsDirectory(auth()->user()->company->id)
                    ->fileAttachmentsVisibility('private'),
            ])->collapsible()->collapsed()->translateLabel(),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Split::make([
                    Section::make([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            TextEntry::make('project.name')
                                ->hiddenLabel()
                                ->icon(ProjectResource::getNavigationIcon())
                                ->hidden(fn ($state) => ! $state)
                                ->url(fn ($record) => ViewProject::getUrl([$record->project_id])),

                            TextEntry::make('client.name')
                                ->hiddenLabel()
                                ->icon(ClientResource::getNavigationIcon())
                                ->hidden(fn ($state) => ! $state)
                                ->url(fn ($record) => ViewClient::getUrl([$record->client_id])),

                            TextEntry::make('task.title')
                                ->hiddenLabel()
                                ->icon(TaskResource::getNavigationIcon())
                                ->hidden(fn ($state) => ! $state)
                                ->url(fn ($record) => ViewTask::getUrl([$record->task_id])),

                        ]),

                        Grid::make([
                            'default' => 2,
                            'md' => 5,
                        ])->schema([
                            TextEntry::make('start')
                                ->translateLabel()
                                ->formatStateUsing(
                                    fn (string $state): string => Carbon::parse($state)
                                        ->tz(PejotaHelper::getUserTimeZone())
                                        ->format(PejotaHelper::getUserDateTimeFormat())
                                ),

                            TextEntry::make('end')
                                ->translateLabel()
                                ->formatStateUsing(
                                    fn (string $state): string => Carbon::parse($state)
                                        ->tz(PejotaHelper::getUserTimeZone())
                                        ->format(PejotaHelper::getUserDateTimeFormat())
                                ),

                            TextEntry::make('duration')
                                ->translateLabel(),
                            TextEntry::make('rate')
                                ->translateLabel(),
                            TextEntry::make('time')
                                ->translateLabel()
                                ->getStateUsing(
                                    fn (Model $record): string => PejotaHelper::formatDuration($record->duration)
                                ),
                        ]),

                        TextEntry::make('description')
                            ->translateLabel()
                            ->formatStateUsing(fn (string $state): HtmlString => new HtmlString($state))
                            ->icon('heroicon-o-document-text')
                            ->hidden(fn ($state) => ! $state),

                    ]),

                    Section::make([
                        Grid::make(2)->schema([
                            IconEntry::make('is_running')
                                ->translateLabel()
                                ->boolean()
                                ->tooltip('If the work session is running'),

                        ]),

                        Actions::make([
                            Action::make('list')
                                ->translateLabel()
                                ->url(
                                    fn (Model $record) => './.'
                                )
                                ->icon('heroicon-o-chevron-left')
                                ->color(Color::Neutral),

                            Action::make('edit')
                                ->translateLabel()
                                ->url(
                                    fn (Model $record) => "{$record->id}/edit"
                                )
                                ->icon('heroicon-o-pencil'),

                            Action::make('finish')
                                ->translateLabel()
                                ->icon(WorkSessionResource::getNavigationIcon())
                                ->color(Color::Red)
                                ->hidden(fn ($record) => ! $record->is_running)
                                ->action(function ($record) {
                                    self::infolistFinish($record);
                                }),
                        ]),
                    ])
                        ->grow(false), // Section at right
                ])
                    ->from('md')
                    ->columnSpanFull(),
            ]);
    }

    public static function cloneCollection(Collection $records)
    {
        $records->each(fn ($record) => self::clone($record));
    }

    public static function clone(WorkSession $record)
    {
        $tz = PejotaHelper::getUserTimeZone();
        $newModel = $record->replicate();
        $newModel->invoice_item_id = null;
        $newModel->start = ($tz ? Carbon::now()->timezone($tz) : Carbon::now())
            ->setTime(
                $record->start->hour,
                $record->start->minute,
                $record->start->second
            );
        $newModel->end = ($tz ? Carbon::now()->timezone($tz) : Carbon::now())
            ->setTime(
                $record->end->hour,
                $record->end->minute,
                $record->end->second
            );
        $newModel->save();

        return redirect(ViewWorkSession::getUrl([$newModel->id]));
    }

    public static function fillClientFromProject(Get $get, Set $set): void
    {
        if (! $get('project')) {
            return;
        }

        $project = Project::find($get('project'));

        if ($project?->client_id) {
            $set('client', $project->client_id);
        }
    }

    public static function fillChildrenFromClient(Get $get, Set $set): void
    {
        if (! $get('client')) {
            return;
        }

        $projectIds = Project::query()
            ->where('client_id', $get('client'))
            ->limit(2)
            ->pluck('id');

        if ($projectIds->count() !== 1) {
            return;
        }

        $set('project', $projectIds->first());

        self::fillTaskFromProject($get, $set);
    }

    public static function fillTaskFromProject(Get $get, Set $set): void
    {
        if (! $get('project')) {
            return;
        }

        $taskIds = Task::query()
            ->where('project_id', $get('project'))
            ->limit(2)
            ->pluck('id');

        if ($taskIds->count() === 1) {
            $set('task', $taskIds->first());
        }
    }

    public static function fillParentsFromTask(Get $get, Set $set): void
    {
        if (! $get('task')) {
            return;
        }

        $task = Task::find($get('task'));

        if ($task?->project_id) {
            $set('project', $task->project_id);
        }

        if ($task?->client_id) {
            $set('client', $task->client_id);
        }
    }

    public static function applyCascade(Get $get, Set $set): void
    {
        $session = new WorkSession([
            'client_id' => $get('client'),
            'project_id' => $get('project'),
            'task_id' => $get('task'),
        ]);

        $set('rate', $session->resolveRate());
        $set('currency', $session->resolveCurrency());
        $set('billable', $session->resolveBillable());
    }

    public static function formSetTimers(bool $fromDuration, Get $get, Set $set): void
    {
        $start = $get('start');
        $end = $get('end');
        $duration = (int) $get('duration');

        if (! $end && ! $duration) {
            return;
        }

        $start = CarbonImmutable::parse($start);

        $end = $fromDuration ? $start->addMinutes($duration) : Carbon::parse($end);

        $set('end', $end->toDateTimeString());

        $duration = (int) $start->diffInMinutes($end, absolute: false);

        $set('duration', $duration);

        $set('time', PejotaHelper::formatDuration($duration));

        $set('is_running', $duration == 0);
    }

    public static function infolistFinish(WorkSession $record): bool
    {
        return $record->finish();
    }
}
