<?php

namespace App\Filament\App\Resources;

use App\Enums\FeatureEnum;
use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Enums\PriorityEnum;
use App\Enums\QuotaEnum;
use App\Enums\RecurrenceAnchorFieldEnum;
use App\Enums\RecurrenceFrequencyEnum;
use App\Enums\RecurrenceGenerationModeEnum;
use App\Enums\RecurrenceStopTypeEnum;
use App\Filament\App\Resources\ClientResource\Pages\ViewClient;
use App\Filament\App\Resources\ProjectResource\Pages\ViewProject;
use App\Filament\App\Resources\TaskResource\Pages\CreateTask;
use App\Filament\App\Resources\TaskResource\Pages\EditTask;
use App\Filament\App\Resources\TaskResource\Pages\ListTasks;
use App\Filament\App\Resources\TaskResource\Pages\ViewTask;
use App\Filament\App\Resources\WorkSessionResource\Pages\CreateWorkSession;
use App\Filament\App\Resources\WorkSessionResource\Pages\ViewWorkSession;
use App\Helpers\PejotaHelper;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use App\Models\WorkSession;
use App\Services\DailyCheckService;
use App\Services\RecurrenceService;
use App\Support\Entitlements;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\SpatieTagsEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Parallax\FilamentComments\Infolists\Components\CommentsEntry;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = MenuSortEnum::TASKS->value;

    public const LIST_COLUMNS = [

    ];

    public static function getModelLabel(): string
    {
        return __('Task');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::DAILY_WORK->value);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with('client')
            ->opened();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $result = [
            'Status' => $record->status->name,
        ];

        if ($record->client) {
            $result['Client'] = $record->client->labelName;
        }

        return $result;
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            Action::make('edit')
                ->hiddenLabel()
                ->url(EditTask::getUrl([$record->id]))
                ->icon('heroicon-o-pencil')
                ->size(Size::ExtraSmall)
                ->tooltip(__('Edit Task')),

            Action::make('session')
                ->hiddenLabel()
                ->url(
                    CreateWorkSession::getUrl([
                        'task' => $record->id,
                    ])
                )
                ->icon('heroicon-o-play')
                ->color(Color::Amber)
                ->size(Size::ExtraSmall)
                ->tooltip(__('Start a session for task')),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return ViewTask::getUrl([$record->id]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(3)->schema([
                    Select::make('client')
                        ->hiddenLabel()
                        ->placeholder(__('Select the client'))
                        ->relationship('client', 'name')
                        ->preload()
                        ->searchable()
                        ->createOptionForm(ClientResource::getSchema()),
                    Select::make('project')
                        ->hiddenLabel()
                        ->placeholder(__('Select the project'))
                        ->relationship(
                            'project',
                            'name',
                            fn (Builder $query, Get $get) => $query->byClient($get('client'))
                                ->where('active', true)
                                ->orderBy('name')

                        )
                        ->searchable()
                        ->preload()
                        ->createOptionForm(ProjectResource::getFormComponents())
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            if (! $get('project')) {
                                return;
                            }

                            $project = Project::find($get('project'));

                            if ($project?->client_id) {
                                $set('client', $project->client_id);
                            }
                        }),
                    Select::make('parent_task')
                        ->hiddenLabel()
                        ->placeholder(__('Select the parent task'))
                        ->relationship(
                            'parent',
                            'title',
                            fn (Builder $query, Get $get) => $query
                                ->byProject($get('project'))
                                ->opened()
                                ->orderBy('title')
                        )
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            if (! $get('parent_task')) {
                                return;
                            }

                            $parent = Task::find($get('parent_task'));

                            if ($parent?->project_id) {
                                $set('project', $parent->project_id);
                            }

                            if ($parent?->client_id) {
                                $set('client', $parent->client_id);
                            }
                        }),
                ]),
                TextInput::make('title')
                    ->hiddenLabel()
                    ->placeholder(__('Title'))
                    ->columnSpanFull()
                    ->required(),

                Section::make(__('Details'))
                    ->collapsible()
                    ->compact()
                    ->schema([
                        SpatieTagsInput::make('tags')
                            ->hiddenLabel()
                            ->placeholder(__('Tags')),

                        RichEditor::make('description')
                            ->hiddenLabel()
                            ->placeholder(__('Description'))
                            ->columnSpanFull()
                            ->extraInputAttributes(
                                ['style' => 'max-height: 300px; overflow: scroll']
                            )
                            ->fileAttachmentsDisk('tasks')
                            ->fileAttachmentsDirectory(PejotaHelper::currentCompany()->id)
                            ->fileAttachmentsVisibility('private'),

                    ]),

                Section::make('checklist')
                    ->label('Checklist')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Repeater::make('checklist')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Item')
                                    ->width('90%'),
                                TableColumn::make(__('Completed')),
                            ])
                            ->addActionLabel(__('Add item'))
                            ->cloneable()
                            ->schema([
                                TextInput::make('item')
                                    ->required(),
                                Checkbox::make('completed')
                                    ->translateLabel(),
                            ])
                            ->defaultItems(0),

                    ]),

                Grid::make([
                    'default' => 2,
                    'md' => 5,
                ])->schema([
                    Select::make('priority')
                        ->translateLabel()
                        ->options(PriorityEnum::class)
                        ->default(PriorityEnum::MEDIUM)
                        ->required(),
                    Select::make('status_id')
                        ->label('Status')
                        ->translateLabel()
                        ->required()
                        ->options(
                            Status::orderBy('sort_order')->pluck('name', 'id')
                        )
                        ->default(Status::orderBy('sort_order')->first()->id),

                    TextInput::make('effort')
                        ->translateLabel()
                        ->numeric(),
                    Select::make('effort_unit')
                        ->translateLabel()
                        ->options([
                            'h' => 'Hours',
                            'm' => 'Minutes',
                        ])
                        ->default('h'),

                    TextInput::make('hourly_rate')
                        ->translateLabel()
                        ->numeric()
                        ->minValue(0)
                        ->helperText(__('Overrides the project/client rate for this task (optional)')),

                ]),

                Grid::make([
                    'default' => 2,
                    'md' => 6,
                ])->schema([
                    //                    Forms\Components\Select::make('date_setting')
                    //                        ->translateLabel()
                    //                        ->options([
                    //                            'all-dates-today' => __('All dates today'),
                    //                            'all-dates-tomorrow' => __('All dates tomorrow'),
                    //                            'due-planned-today' => __('Due and planned dates today'),
                    //                            'due-planned-tomorrow' => __('Due and planned dates tomorrow'),
                    //                        ])
                    //                        ->live()
                    //                        ->afterStateUpdated(function ($state, $get, $set) {
                    //                            $today = Carbon::today(PejotaHelper::getUserTimeZone())->toDayDateTimeString();
                    //                            match ($state) {
                    //                                'all-dates-today' => function () use ($set, $today) {
                    //                                    $set('due_date', $today);
                    //                                    $set('planned_start', $today);
                    //                                    $set('planned_end', $today);
                    //                                    $set('actual_start', $today);
                    //                                    $set('actual_end', $today);
                    //                                },
                    //                            };
                    //                        }),
                    DatePicker::make('due_date')
                        ->columnSpan([
                            'default' => 2,
                            'md' => 1,
                        ])
                        ->translateLabel(),
                    DatePicker::make('planned_start')
                        ->translateLabel(),
                    DatePicker::make('planned_end')
                        ->translateLabel(),
                    DatePicker::make('actual_start')
                        ->translateLabel(),
                    DatePicker::make('actual_end')
                        ->translateLabel(),

                    Toggle::make('is_continuous')
                        ->label(__('Track as a daily habit'))
                        ->helperText(__('Turn this task into a daily habit: it stays pinned to the top of the active list and you check it in each day to build a streak.'))
                        ->inline(false),

                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $columns = self::getTableColumns();
        if (PejotaHelper::isMobile()) {
            $columns = [
                Split::make($columns)
                    ->from('sm'),
            ];
        }

        return $table
            ->groups([
                'client.name',
                'project.name',
                'due_date',
                'status.name',
                Group::make('is_continuous')
                    ->label(__('Daily check'))
                    ->getTitleFromRecordUsing(fn (Task $record): string => $record->is_continuous ? __('Daily checks') : __('Tasks')),
            ])
            ->recordClasses(fn (Task $record): ?string => DailyCheckService::recordClasses($record))
            ->striped()
            ->columns(
                $columns
            )
            ->filtersFormColumns(4)
            ->filters([
                SelectFilter::make('status')
                    ->relationship('status', 'name')
                    ->multiple(true)
                    ->preload(),
                SelectFilter::make('priority')
                    ->translateLabel()
                    ->options(PriorityEnum::class)
                    ->multiple(true),
                SelectFilter::make('client')
                    ->translateLabel()
                    ->relationship('client', 'name'),
                SelectFilter::make('project')
                    ->translateLabel()
                    ->relationship('project', 'name'),
                Filter::make('is_continuous')
                    ->label(__('Continuous'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('is_continuous', true)),
                Filter::make('hide_continuous')
                    ->label(__('Hide daily checks'))
                    ->toggle()
                    ->query(function (Builder $query, $livewire): Builder {
                        if (($livewire->activeTab ?? null) === 'daily_checks') {
                            return $query;
                        }

                        return $query->where('is_continuous', false);
                    }),
                Filter::make('recurring')
                    ->label(__('Recurring'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('recurrence_id')),
                Filter::make('due_date_not_empty')
                    ->schema([
                        ToggleButtons::make('due_date')
                            ->translateLabel()
                            ->inline()
                            ->options([
                                'not_empty' => __('Has due date'),
                                'empty' => __('No due date'),
                            ]),
                    ])
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['due_date'] == 'not_empty',
                                fn (Builder $query): Builder => $query->whereNotNull('due_date')
                            )
                            ->when(
                                $data['due_date'] == 'empty',
                                fn (Builder $query): Builder => $query->whereNull('due_date')
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['due_date']) {
                            return $data['due_date'] == 'not_empty' ? __('Has due date') : __('No due date');
                        }

                        return null;
                    }),
                Filter::make('due_date')
                    ->columnSpan(2)
                    ->schema([
                        DatePicker::make('from_due_date')
                            ->translateLabel()
                            ->inlineLabel(),
                        DatePicker::make('to_due_date')
                            ->translateLabel()
                            ->inlineLabel(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_due_date'],
                                fn (Builder $query, $date): Builder => $query->where(
                                    'due_date',
                                    '>=',
                                    $data['from_due_date']
                                )
                            )
                            ->when(
                                $data['to_due_date'],
                                fn (Builder $query, $date): Builder => $query->where(
                                    'due_date',
                                    '<=',
                                    $data['to_due_date']
                                )
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['from_due_date'] || $data['to_due_date']) {
                            return __('Due date').': '.$data['from_due_date'].' - '.$data['to_due_date'];
                        }

                        return null;
                    }),

                Filter::make('planned_end_not_empty')
                    ->schema([
                        ToggleButtons::make('planned_end')
                            ->translateLabel()
                            ->inline()
                            ->options([
                                'not_empty' => __('Has planned end date'),
                                'empty' => __('No planned end date'),
                            ]),
                    ])
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['planned_end'] == 'not_empty',
                                fn (Builder $query): Builder => $query->whereNotNull('planned_end')
                            )
                            ->when(
                                $data['planned_end'] == 'empty',
                                fn (Builder $query): Builder => $query->whereNull('planned_end')
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['planned_end']) {
                            return $data['planned_end'] == 'not_empty' ? __('Has planned end date') : __('No planned end date');
                        }

                        return null;
                    }),

                Filter::make('planned_end')
                    ->columnSpan(2)
                    ->schema([
                        DatePicker::make('from_planned_end')
                            ->translateLabel()
                            ->inlineLabel(),
                        DatePicker::make('to_planned_end')
                            ->translateLabel()
                            ->inlineLabel(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_planned_end'],
                                fn (Builder $query, $date): Builder => $query->where(
                                    DB::raw('DATE(planned_end)'),
                                    '>=',
                                    $data['from_planned_end']
                                )
                            )
                            ->when(
                                $data['to_planned_end'],
                                fn (Builder $query, $date): Builder => $query->where(
                                    DB::raw('DATE(planned_end)'),
                                    '<=',
                                    $data['to_planned_end']
                                )
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['from_planned_end'] || $data['to_planned_end']) {
                            return __('Due date').': '.$data['from_planned_end'].' - '.$data['to_planned_end'];
                        }

                        return null;
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    CommentsAction::make(),
                    Action::make(__('Start Session'))
                        ->tooltip(__('Start a new session for this task'))
                        ->icon('heroicon-o-play')
                        ->color(Color::Amber)
//                        ->form(WorkSessionResource::getFormSchema())
//                        ->fillForm(function(Task $task) {
//                            return CreateWorkSession::getFillFormArray($task);
//                        }),
                        ->url(fn ($record) => CreateWorkSession::getUrl([
                            'task' => $record->id,
                        ])),
                    Action::make('markDoneToday')
                        ->label(__('Done today'))
                        ->tooltip(__('Mark this daily task as done today'))
                        ->icon('heroicon-o-check-circle')
                        ->color(Color::Green)
                        ->visible(fn (Task $record): bool => $record->isDailyCheck() && ! $record->isDoneToday())
                        ->action(fn (Task $record) => DailyCheckService::toggle($record)),
                    EditAction::make(),
                    Action::make(__('Clone'))
                        ->tooltip(
                            __(
                                'Clone this record with same details but the dates, then open the form to you fill dates'
                            )
                        )
                        ->icon('heroicon-o-document-duplicate')
                        ->color(Color::Amber)
                        ->visible(fn (): bool => Entitlements::withinQuota(QuotaEnum::TasksPerMonth, Task::createdThisMonthCount()))
                        ->action(fn (Task $record) => self::clone($record)),

                    self::configureMakeRecurringAction(Action::make('makeRecurring')),
                    Action::make('stopSeries')
                        ->label(__('Stop series'))
                        ->icon('heroicon-o-no-symbol')
                        ->color(Color::Red)
                        ->requiresConfirmation()
                        ->visible(fn (Task $record): bool => $record->recurrence?->is_active === true)
                        ->action(function (Task $record): void {
                            app(RecurrenceService::class)->stopSeries($record->recurrence);

                            Notification::make()
                                ->title(__('Series stopped'))
                                ->success()
                                ->send();
                        }),

                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    BulkActionGroup::make(
                        self::getPostponeActions('planned_start'),
                    )
                        ->label('Postpone planned start')
                        ->translateLabel()
                        ->icon('heroicon-o-calendar'),

                    BulkActionGroup::make(
                        self::getPostponeActions('planned_end'),
                    )
                        ->label('Postpone planned end')
                        ->translateLabel()
                        ->icon('heroicon-o-calendar'),

                    BulkActionGroup::make(
                        self::getPostponeActions('due_date'),
                    )
                        ->label('Postpone due date')
                        ->translateLabel()
                        ->icon('heroicon-o-calendar'),

                    BulkAction::make(__('Clone selected'))
                        ->tooltip(__('Clone this session with same time and details, updating to current date'))
                        ->icon('heroicon-o-document-duplicate')
                        ->color(Color::Amber)
                        ->visible(fn (): bool => Entitlements::withinQuota(QuotaEnum::TasksPerMonth, Task::createdThisMonthCount()))
                        ->action(fn (\Illuminate\Support\Collection $records) => self::cloneCollection($records))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->persistColumnSearchesInSession();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->name('Task')
            ->components([
                Flex::make([
                    Grid::make(1)->schema([
                        Section::make([
                            SpatieTagsEntry::make('tags')
                                ->label('')
                                ->icon('heroicon-o-tag'),

                            Grid::make(2)->schema([
                                TextEntry::make('project.name')
                                    ->label('')
                                    ->icon('heroicon-o-presentation-chart-bar')
                                    ->hidden(fn ($state) => ! $state)
                                    ->url(fn ($record) => ViewProject::getUrl([$record->project_id])),

                                TextEntry::make('client.name')
                                    ->label('')
                                    ->icon('heroicon-o-building-office')
                                    ->hidden(fn ($state) => ! $state)
                                    ->url(fn ($record) => ViewClient::getUrl([$record->client_id])),

                                TextEntry::make('parent.title')
                                    ->label('')
                                    ->icon(TaskResource::getNavigationIcon())
                                    ->hidden(fn ($state) => ! $state)
                                    ->url(fn ($record) => ViewTask::getUrl([$record->parent_id])),
                            ]),

                            TextEntry::make('description')
                                ->translateLabel()
                                ->formatStateUsing(fn (string $state): HtmlString => new HtmlString($state))
                                ->icon('heroicon-o-document-text'),

                            Grid::make([
                                'default' => 2,
                                'md' => 4,
                            ])->schema([
                                TextEntry::make('planned_start')
                                    ->translateLabel()
                                    ->date(PejotaHelper::getUserDateFormat())
                                    ->icon('heroicon-o-calendar'),
                                TextEntry::make('planned_end')
                                    ->translateLabel()
                                    ->date(PejotaHelper::getUserDateFormat())
                                    ->icon('heroicon-o-calendar'),

                                TextEntry::make('actual_start')
                                    ->translateLabel()
                                    ->date(PejotaHelper::getUserDateFormat())
                                    ->icon('heroicon-o-calendar'),

                                TextEntry::make('actual_end')
                                    ->translateLabel()
                                    ->date(PejotaHelper::getUserDateFormat())
                                    ->icon('heroicon-o-calendar'),
                            ]),
                        ]),

                        Tabs::make('Tabs')->schema([
                            Tab::make('Checklist')
                                ->badge(fn (Model $record): int => $record->checklist ? count($record->checklist) : 0)
                                ->schema([
                                    RepeatableEntry::make('checklist')
                                        ->contained(false)
                                        ->hiddenLabel()
                                        ->schema([
                                            TextEntry::make('item')
                                                ->hiddenLabel()
                                                ->formatStateUsing(function ($state, $component): HtmlString {
                                                    if (self::getStateCompleted($component)) {
                                                        $state = '<s>'.$state.'</s>';
                                                    }

                                                    return new HtmlString($state);
                                                })
                                                ->prefixAction(fn ($component) => Action::make('checkCompleted')
                                                    ->icon(
                                                        self::getStateCompleted(
                                                            $component
                                                        ) ? 'heroicon-o-check' : 'heroicon-o-stop'
                                                    )
                                                    ->color(
                                                        self::getStateCompleted($component) ? Color::Green : Color::Gray
                                                    )
                                                    ->action(function (Model $record, $component) {
                                                        $index = explode('.', $component->getStatePath())[1];
                                                        $checklist = $record->checklist;
                                                        $checklist[$index]['completed'] = ! $record->checklist[$index]['completed'];
                                                        $record->checklist = $checklist;
                                                        $record->save();
                                                    })
                                                ),
                                        ]),
                                ]),

                            Tab::make('Subtasks')
                                ->translateLabel()
                                ->badge(fn (Model $record): int => $record->children->count())
                                ->schema([
                                    RepeatableEntry::make('children')
                                        ->hiddenLabel()
                                        ->contained(false)
                                        ->getStateUsing(function (Model $record) {
                                            $items = $record->children;
                                            foreach ($items as $key => $value) {
                                                $value->sort = $key;
                                            }

                                            return $items;
                                        })
                                        ->schema([
                                            Grid::make(12)->schema([
                                                IconEntry::make('priority')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->icon(fn ($state) => PriorityEnum::from($state)->getIcon())
                                                    ->color(fn ($state) => PriorityEnum::from($state)->getColor())
                                                    ->tooltip(fn ($state) => PriorityEnum::from($state)->getLabel()),

                                                TextEntry::make('status.name')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->badge()
                                                    ->color(
                                                        fn (Model $record): array => Color::generateV3Palette($record->status->color)
                                                    ),
                                                TextEntry::make('title')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->columnSpan(8)
                                                    ->action(
                                                        Action::make('view')
                                                            ->schema(fn (Model $record) => self::infolist(
                                                                (new Schema)->record($record)
                                                            ))
                                                            ->modalWidth(Width::FitContent)
                                                            ->modalCancelAction(false)
                                                            ->modalSubmitActionLabel('Close')
                                                    ),
                                                TextEntry::make('due_date')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->date(PejotaHelper::getUserDateFormat())
                                                    ->icon('heroicon-o-calendar')
                                                    ->columnSpan(2),
                                            ]),
                                        ]),
                                ]),
                        ]),

                        Tabs::make('Tabs')->schema([
                            Tab::make('Daily checks')
                                ->label(__('Daily checks'))
                                ->visible(fn (Model $record): bool => (bool) $record->is_continuous)
                                ->badge(fn (Model $record): int => $record->currentStreak())
                                ->schema([
                                    TextEntry::make('current_streak')
                                        ->label(__('Current streak'))
                                        ->icon('heroicon-o-fire')
                                        ->color(fn (Model $record): string => $record->currentStreak() > 0 ? 'success' : 'gray')
                                        ->getStateUsing(fn (Model $record): string => $record->currentStreak().' '.__('days')),

                                    RepeatableEntry::make('taskCompletions')
                                        ->label(__('Check-in history'))
                                        ->contained(false)
                                        ->columnSpanFull()
                                        ->getStateUsing(fn (Model $record) => $record->taskCompletions()->orderByDesc('completed_on')->get())
                                        ->schema([
                                            TextEntry::make('completed_on')
                                                ->hiddenLabel()
                                                ->date(PejotaHelper::getUserDateFormat())
                                                ->icon('heroicon-o-check-circle')
                                                ->color(Color::Green),
                                        ]),
                                ]),

                            Tab::make('Comments')
                                ->translateLabel()
                                ->badge(fn (Model $record): int => $record->filamentComments->count())
                                ->schema([
                                    CommentsEntry::make('filament_comments')
                                        ->columnSpanFull(),
                                ]),

                            Tab::make('Sessions')
                                ->translateLabel()
                                ->badge(fn (Model $record): int => $record->workSessions->count())
                                ->schema([
                                    RepeatableEntry::make('workSessions')
                                        ->label('')
                                        ->columnSpanFull()
                                        ->getStateUsing(function (Model $record) {
                                            $items = $record->workSessions->sortByDesc('start');
                                            foreach ($items as $key => $value) {
                                                $value->sort = $key;
                                            }

                                            return $items;
                                        })
                                        ->schema([
                                            Grid::make(6)->schema([
                                                TextEntry::make('start')
                                                    ->label('Started at')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->dateTime(PejotaHelper::getUserDateTimeFormat())
                                                    ->timezone(PejotaHelper::getUserTimeZone())
                                                    ->url(
                                                        fn ($record) => ViewWorkSession::getUrl([
                                                            'record' => $record->id,
                                                        ])
                                                    ),

                                                TextEntry::make('duration')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->icon(
                                                        fn (Model $record
                                                        ): string => $record->is_running ? '' : 'heroicon-o-clock'
                                                    )
                                                    ->formatStateUsing(
                                                        fn ($state): string => PejotaHelper::formatDuration($state)
                                                    )
                                                    ->hidden(fn ($record) => $record->is_running),

                                                Actions::make([
                                                    Action::make('stop')
                                                        ->translateLabel()
                                                        ->icon('heroicon-o-stop')
                                                        ->color(Color::Red)
                                                        ->requiresConfirmation()
                                                        ->action(function (WorkSession $record) {
                                                            unset($record->sort);

                                                            return $record->finish();
                                                        }),
                                                ])
                                                    ->hidden(fn ($record) => ! $record->is_running),

                                                TextEntry::make('title')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->columnSpan(fn (Model $record): int => $record->description ? 2 : 4
                                                    ),
                                                IconEntry::make('description')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->columnSpan(2)
                                                    ->icon('heroicon-o-information-circle')
                                                    ->color(Color::Green)
                                                    ->tooltip(
                                                        fn (string $state): HtmlString => new HtmlString($state)
                                                    )
                                                    ->visible(fn ($state) => $state),
                                            ]),
                                        ]),
                                ]),

                            Tab::make('History')
                                ->translateLabel()
                                ->badge(fn (Model $record): int => $record->activities->count())
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('created_at')
                                            ->translateLabel()
                                            ->inlineLabel()
                                            ->dateTime()
                                            ->timezone(PejotaHelper::getUserTimeZone()),
                                        TextEntry::make('updated_at')
                                            ->translateLabel()
                                            ->inlineLabel()
                                            ->dateTime()
                                            ->timezone(PejotaHelper::getUserTimeZone()),

                                    ]),

                                    RepeatableEntry::make('activities')
                                        ->translateLabel()
                                        ->columnSpanFull()
                                        ->contained(false)
                                        ->schema([
                                            Grid::make(4)->schema([
                                                TextEntry::make('created_at')
                                                    ->hiddenLabel()
                                                    ->icon('heroicon-o-chevron-double-right')
                                                    ->dateTime()
                                                    ->timezone(PejotaHelper::getUserTimeZone()),
                                                TextEntry::make('description')
                                                    ->hiddenLabel(),
                                                TextEntry::make('causer.name')
                                                    ->hiddenLabel(),
                                                TextEntry::make('properties.attributes')
                                                    ->hiddenLabel()
                                                    ->getStateUsing(
                                                        fn (Model $record): array => [
                                                            $record->properties->get(
                                                                'attributes'
                                                            )['status.name'],
                                                        ]
                                                    ),
                                            ]),
                                        ]),
                                ]),
                        ]),
                    ]),

                    /***************************
                     * Sidebar infolist with priority and status
                     ******************************/
                    Section::make([
                        Grid::make([
                            'default' => 2,
                        ])->schema([
                            IconEntry::make('priority')
                                ->translateLabel()
                                ->icon(fn ($state) => PriorityEnum::from($state)->getIcon())
                                ->color(fn ($state) => PriorityEnum::from($state)->getColor())
                                ->tooltip(fn ($state) => PriorityEnum::from($state)->getLabel()),

                            TextEntry::make('status.name')
                                ->badge()
                                ->color(fn (Model $record): array => Color::generateV3Palette($record->status->color))
                                ->hintActions([
                                    Action::make('change_status')
                                        ->hiddenLabel()
                                        ->icon('heroicon-o-pencil')
                                        ->color(Color::generateV3Palette('#ACA'))
                                        ->button()
                                        ->tooltip(__('Change status'))
                                        ->action(function (Model $record, array $data): void {
                                            $record->update($data);
                                        })
                                        ->schema([
                                            Select::make('status_id')
                                                ->label('Status')
                                                ->options(fn (): array => Status::all()->pluck('name', 'id')->toArray()),
                                        ]),
                                ]),

                            TextEntry::make('due_date')
                                ->translateLabel()
                                ->inlineLabel()
                                ->date(PejotaHelper::getUserDateFormat())
                                ->icon('heroicon-o-exclamation-triangle')
                                ->columnSpanFull(),

                            TextEntry::make('effort')
                                ->translateLabel()
                                ->label('Estimated')
                                ->inlineLabel()
                                ->icon('heroicon-o-variable')
                                ->formatStateUsing(
                                    fn (Model $record): string => $record->effort.' '.$record->effort_unit
                                ),

                            TextEntry::make('workSessions')
                                ->label('Sessions')
                                ->translateLabel()
                                ->inlineLabel()
                                ->icon('heroicon-o-clock')
                                ->formatStateUsing(
                                    fn (Model $record): string => PejotaHelper::formatDuration(
                                        $record->workSessions->sum('duration')
                                    )
                                ),
                        ]),

                        Actions::make([
                            Action::make('list')
                                ->translateLabel()
                                ->hiddenLabel(PejotaHelper::isMobile())
                                ->url(
                                    fn (Model $record) => './.'
                                )
                                ->icon('heroicon-o-chevron-left')
                                ->color(Color::Neutral),

                            Action::make('edit')
                                ->translateLabel()
                                ->hiddenLabel(PejotaHelper::isMobile())
                                ->url(
                                    fn (Model $record) => "{$record->id}/edit"
                                )
                                ->icon('heroicon-o-pencil'),

                            Action::make('subtask')
                                ->translateLabel()
                                ->hiddenLabel(PejotaHelper::isMobile())
                                ->icon(TaskResource::getNavigationIcon())
                                ->color(Color::Green)
                                ->modal(true)
                                ->url(fn ($record) => CreateTask::getUrl([
                                    'parent' => $record->id,
                                ])),

                            Action::make('session')
                                ->translateLabel()
                                ->hiddenLabel(PejotaHelper::isMobile())
                                ->icon(WorkSessionResource::getNavigationIcon())
                                ->color(Color::Amber)
                                ->modal(true)
                                ->url(fn ($record) => CreateWorkSession::getUrl([
                                    'task' => $record->id,
                                ])),
                        ]),
                    ])
                        ->grow(false), // Section at right
                ])
                    ->from('md')
                    ->columnSpanFull(),

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
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'view' => ViewTask::route('/{record}'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }

    public static function getStateCompleted($component)
    {
        $index = explode('.', $component->getStatePath())[1];

        $data = $component
            ->getContainer()
            ->getParentComponent()
            ->getState()[$index];

        return $data['completed'];
    }

    protected static function getPostponeActions($field): array
    {
        return [
            BulkAction::make($field.'_postpone_today')
                ->label('Today')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn (Collection $records) => $records->each->postpone($field, 'today')),
            BulkAction::make($field.'_postpone_1_day')
                ->label('1 day')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn (Collection $records) => $records->each->postpone($field, '1 day')),
            BulkAction::make($field.'_postpone_3_days')
                ->label('3 days')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn (Collection $records) => $records->each->postpone($field, '3 days')),
            BulkAction::make($field.'_postpone_5_days')
                ->label('5 days')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn (Collection $records) => $records->each->postpone($field, '5 days')),
            BulkAction::make($field.'_postpone_1_week')
                ->label('1 week')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn (Collection $records) => $records->each->postpone($field, '1 week')),
            BulkAction::make($field.'_postpone_2_weeks')
                ->label('2 weeks')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn (Collection $records) => $records->each->postpone($field, '2 weeks')),
            BulkAction::make($field.'_postpone_1_month')
                ->label('1 month')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn (Collection $records) => $records->each->postpone($field, '1 month')),
            BulkAction::make($field.'_postpone_custom')
                ->label('Custom')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->form([
                    DatePicker::make($field)
                        ->translateLabel()
                        ->required(),
                ])
                ->action(function ($data, Collection $records) use ($field) {
                    foreach ($records as $record) {
                        $record->{$field} = $data[$field];
                        $record->save();
                    }
                }),

        ];
    }

    public static function getTableColumns(): array
    {
        //        dd(PejotaHelper::getUserTaskListDefaultColumns());
        $isMobile = PejotaHelper::isMobile();

        return [
            IconColumn::make('priority')
                ->translateLabel()
                ->extraHeaderAttributes(['class' => 'column-header-no-label'])
                ->sortable()
                ->icon(fn ($state) => PriorityEnum::from($state)->getIcon())
                ->color(fn ($state) => PriorityEnum::from($state)->getColor())
                ->tooltip(fn ($state) => PriorityEnum::from($state)->getLabel())
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('priority', PejotaHelper::getUserTaskListDefaultColumns()),
                ),
            IconColumn::make('work_session')
                ->translateLabel()
                ->wrapHeader()
                ->extraHeaderAttributes(['class' => 'column-header-no-label'])
                ->boolean()
                ->getStateUsing(fn ($record) => $record->workSessions()->where('is_running', true)->count())
                ->falseColor(Color::generateV3Palette('#ddd'))
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('work_session', PejotaHelper::getUserTaskListDefaultColumns()),
                ),
            DailyCheckService::doneTodayColumn()
                ->toggleable(),
            SelectColumn::make('status_id')
                ->label('Status')
                ->options(fn (): array => Status::all()->pluck('name', 'id')->toArray())
                ->sortable()
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('status_id', PejotaHelper::getUserTaskListDefaultColumns()),
                )
                ->selectablePlaceholder(false),
            ColorColumn::make('status.color')
                ->label('Status Color')
                ->extraHeaderAttributes(['class' => 'column-header-no-label'])
                ->tooltip(fn (Model $record) => $record->status->name)
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('status_color', PejotaHelper::getUserTaskListDefaultColumns()),
                ),
            TextColumn::make('title')
                ->translateLabel()
                ->wrap()
                ->weight(FontWeight::Bold)
                ->searchable(),
            TextColumn::make('due_date')
                ->translateLabel()
                ->description(fn () => $isMobile ? __('Due date') : null, 'above')
                ->wrapHeader()
                ->date(PejotaHelper::getUserDateFormat())
                ->sortable()
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('due_date', PejotaHelper::getUserTaskListDefaultColumns()),
                ),
            TextColumn::make('effort')
                ->translateLabel()
                ->description(fn () => $isMobile ? __('Effort') : null, 'above')
                ->formatStateUsing(fn (Model $record): string => $record->effort.' '.$record->effort_unit)
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('effort', PejotaHelper::getUserTaskListDefaultColumns()),
                ),
            TextColumn::make('planned_start')
                ->translateLabel()
                ->description(fn () => $isMobile ? __('Planned start') : null, 'above')
                ->wrapHeader()
                ->date(PejotaHelper::getUserDateFormat())
                ->sortable()
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('planned_start', PejotaHelper::getUserTaskListDefaultColumns()),
                ),
            TextColumn::make('planned_end')
                ->translateLabel()
                ->description(fn () => $isMobile ? __('Planned end') : null, 'above')
                ->wrapHeader()
                ->date(PejotaHelper::getUserDateFormat())
                ->sortable()
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('planned_end', PejotaHelper::getUserTaskListDefaultColumns()),
                ),
            TextColumn::make('client')
                ->translateLabel()
                ->formatStateUsing(fn (Model $record): string => $record->client->labelName ?? $record->client->name)
                ->sortable()
                ->wrap()
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array(
                        'client.labelName',
                        PejotaHelper::getUserTaskListDefaultColumns()
                    ),
                ),
            TextColumn::make('project.name')
                ->translateLabel()
                ->sortable()
                ->wrap()
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('project.name', PejotaHelper::getUserTaskListDefaultColumns()),
                ),
            SpatieTagsColumn::make('tags')
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('tags', PejotaHelper::getUserTaskListDefaultColumns()),
                ),
            DailyCheckService::streakColumn()
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('streak', PejotaHelper::getUserTaskListDefaultColumns()),
                ),
            TextColumn::make('created_at')
                ->translateLabel()
                ->description(fn () => $isMobile ? __('Created at') : null, 'above')
                ->dateTime()
                ->timezone(PejotaHelper::getUserTimeZone())
                ->sortable()
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('created_at', PejotaHelper::getUserTaskListDefaultColumns()),
                ),
            TextColumn::make('updated_at')
                ->translateLabel()
                ->description(fn () => $isMobile ? __('Updated at') : null, 'above')
                ->dateTime()
                ->timezone(PejotaHelper::getUserTimeZone())
                ->sortable()
                ->toggleable(
                    isToggledHiddenByDefault: ! in_array('updated_at', PejotaHelper::getUserTaskListDefaultColumns()),
                ),
        ];
    }

    public static function cloneCollection(\Illuminate\Support\Collection $records)
    {
        if (! Entitlements::withinQuota(QuotaEnum::TasksPerMonth, Task::createdThisMonthCount())) {
            self::notifyPlanLimitReached();

            return;
        }

        $records->each(fn ($record) => self::clone($record));
    }

    public static function clone(Task $record)
    {
        if (! Entitlements::withinQuota(QuotaEnum::TasksPerMonth, Task::createdThisMonthCount())) {
            self::notifyPlanLimitReached();

            return;
        }

        $newModel = $record->replicate();
        $newModel->due_date = null;
        $newModel->planned_end = null;
        $newModel->planned_start = null;
        $newModel->actual_end = null;
        $newModel->actual_start = null;
        $newModel->status_id = Status::select('id')->orderBy('sort_order')->first()?->id;
        $newModel->save();

        return redirect(EditTask::getUrl([$newModel->id]));
    }

    private static function notifyPlanLimitReached(): void
    {
        Notification::make()
            ->warning()
            ->title(__('Plan limit reached'))
            ->body(__('You have reached your current plan limit. Upgrade to add more.'))
            ->send();
    }

    public static function configureMakeRecurringAction(Action $action): Action
    {
        return $action
            ->label(__('Make recurring'))
            ->icon('heroicon-o-arrow-path')
            ->color(Color::Indigo)
            ->visible(fn (Task $record): bool => $record->recurrence_id === null && Entitlements::allows(FeatureEnum::RecurringTasks))
            ->schema(self::makeRecurringFormSchema())
            ->action(fn (Task $record, array $data) => self::enableRecurrenceFromForm($record, $data));
    }

    /**
     * @return array<int, Component>
     */
    private static function makeRecurringFormSchema(): array
    {
        return [
            Select::make('frequency')
                ->label(__('Frequency'))
                ->options(RecurrenceFrequencyEnum::class)
                ->default(RecurrenceFrequencyEnum::Monthly)
                ->required(),
            TextInput::make('interval')
                ->label(__('Every (interval)'))
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required(),
            Select::make('anchor_field')
                ->label(__('Apply to date'))
                ->options(RecurrenceAnchorFieldEnum::class)
                ->default(RecurrenceAnchorFieldEnum::DueDate)
                ->required(),
            TextInput::make('offset_days')
                ->label(__('Planned-end lead days (when both)'))
                ->numeric()
                ->default(0),
            Select::make('generation_mode')
                ->label(__('Generation'))
                ->options(RecurrenceGenerationModeEnum::class)
                ->default(RecurrenceGenerationModeEnum::ByDate)
                ->required(),
            Select::make('stop_type')
                ->label(__('Stop condition'))
                ->options(RecurrenceStopTypeEnum::class)
                ->default(RecurrenceStopTypeEnum::Never)
                ->live()
                ->required(),
            DatePicker::make('until_date')
                ->label(__('Until date'))
                ->visible(fn (Get $get): bool => $get('stop_type') === RecurrenceStopTypeEnum::UntilDate->value),
            TextInput::make('max_count')
                ->label(__('Number of occurrences'))
                ->numeric()
                ->minValue(1)
                ->visible(fn (Get $get): bool => $get('stop_type') === RecurrenceStopTypeEnum::Count->value),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function enableRecurrenceFromForm(Task $record, array $data): void
    {
        if (! Entitlements::allows(FeatureEnum::RecurringTasks)) {
            self::notifyPlanLimitReached();

            return;
        }

        $frequency = $data['frequency'] instanceof RecurrenceFrequencyEnum
            ? $data['frequency']
            : RecurrenceFrequencyEnum::from($data['frequency']);
        $anchorField = $data['anchor_field'] instanceof RecurrenceAnchorFieldEnum
            ? $data['anchor_field']
            : RecurrenceAnchorFieldEnum::from($data['anchor_field']);
        $generationMode = $data['generation_mode'] instanceof RecurrenceGenerationModeEnum
            ? $data['generation_mode']
            : RecurrenceGenerationModeEnum::from($data['generation_mode']);
        $stopType = $data['stop_type'] instanceof RecurrenceStopTypeEnum
            ? $data['stop_type']
            : RecurrenceStopTypeEnum::from($data['stop_type']);

        app(RecurrenceService::class)->enableForTask($record, [
            'frequency' => $frequency,
            'interval' => (int) $data['interval'],
            'anchor_field' => $anchorField,
            'offset_days' => (int) ($data['offset_days'] ?? 0),
            'generation_mode' => $generationMode,
            'stop_type' => $stopType,
            'until_date' => $data['until_date'] ?? null,
            'max_count' => isset($data['max_count']) ? (int) $data['max_count'] : null,
        ]);

        Notification::make()
            ->title(__('Recurrence enabled'))
            ->success()
            ->send();
    }
}
