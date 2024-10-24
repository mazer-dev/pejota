<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Enums\PriorityEnum;
use App\Filament\App\Resources\ClientResource\Pages\ViewClient;
use App\Filament\App\Resources\ProjectResource\Pages\ViewProject;
use App\Filament\App\Resources\TaskResource\Pages;
use App\Filament\App\Resources\WorkSessionResource\Pages\CreateWorkSession;
use App\Filament\App\Resources\WorkSessionResource\Pages\ViewWorkSession;
use App\Helpers\PejotaHelper;
use App\Models\Status;
use App\Models\Task;
use App\Models\WorkSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\SpatieTagsEntry;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;
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

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = MenuSortEnum::TASKS->value;

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
                ->url(Pages\EditTask::getUrl([$record->id]))
                ->icon('heroicon-o-pencil')
                ->size(ActionSize::ExtraSmall)
                ->tooltip(__('Edit Task')),

            Action::make('session')
                ->hiddenLabel()
                ->url(CreateWorkSession::getUrl([
                    'task' => $record->id,
                ]))
                ->icon('heroicon-o-play')
                ->color(Color::Amber)
                ->size(ActionSize::ExtraSmall)
                ->tooltip(__('Start a session for task')),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return Pages\ViewTask::getUrl([$record->id]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('client')
                        ->translateLabel()
                        ->relationship('client', 'name')
                        ->preload()->searchable(),
                    Forms\Components\Select::make('project')
                        ->label('Project')
                        ->translateLabel()
                        ->relationship(
                            'project',
                            'name',
                            fn(Builder $query, Forms\Get $get) => $query->byClient($get('client'))->orderBy('name')
                        )
                        ->searchable()->preload(),
                    Forms\Components\Select::make('parent_task')
                        ->translateLabel()
                        ->relationship('parent', 'title')
                        ->searchable(),
                ]),
                Forms\Components\TextInput::make('title')
                    ->translateLabel()
                    ->columnSpanFull()
                    ->required(),

                Forms\Components\Section::make(__('Details'))
                    ->collapsible()
                    ->compact()
                    ->schema([
                        Forms\Components\SpatieTagsInput::make('tags'),

                        Forms\Components\RichEditor::make('description')
                            ->translateLabel()
                            ->columnSpanFull()
                            ->extraInputAttributes(
                                ['style' => 'max-height: 300px; overflow: scroll'])
                            ->fileAttachmentsDisk('tasks')
                            ->fileAttachmentsDirectory(auth()->user()->company->id)
                            ->fileAttachmentsVisibility('private'),

                    ]),

                Forms\Components\Section::make('checklist')
                    ->label('Checklist')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TableRepeater::make('checklist')
                            ->hiddenLabel()
                            ->addActionLabel(__('Add item'))
                            ->cloneable()
                            ->schema([
                                Forms\Components\TextInput::make('item')
                                    ->required(),
                                Forms\Components\Checkbox::make('completed')
                                    ->translateLabel(),
                            ])
                            ->defaultItems(0)
                            ->colStyles([
                                'item' => 'width:90%',
                            ]),

                    ]),

                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\Select::make('priority')
                        ->translateLabel()
                        ->options(PriorityEnum::class)
                        ->default(PriorityEnum::MEDIUM)
                        ->required(),
                    Forms\Components\Select::make('status_id')
                        ->label('Status')
                        ->translateLabel()
                        ->required()
                        ->options(
                            Status::orderBy('sort_order')->pluck('name', 'id')
                        )
                        ->default(Status::orderBy('sort_order')->first()->id),

                    Forms\Components\TextInput::make('effort')
                        ->translateLabel()
                        ->numeric(),
                    Forms\Components\Select::make('effort_unit')
                        ->translateLabel()
                        ->options([
                            'h' => 'Hours',
                            'm' => 'Minutes',
                        ])
                        ->default('h'),

                ]),

                Forms\Components\Grid::make(5)->schema([
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
                    Forms\Components\DatePicker::make('due_date')
                        ->translateLabel(),
                    Forms\Components\DatePicker::make('planned_start')
                        ->translateLabel(),
                    Forms\Components\DatePicker::make('planned_end')
                        ->translateLabel(),
                    Forms\Components\DatePicker::make('actual_start')
                        ->translateLabel(),
                    Forms\Components\DatePicker::make('actual_end')
                        ->translateLabel(),

                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                'client.name',
                'project.name',
                'due_date',
                'status.name',
            ])
            ->striped()
            ->columns([
                Tables\Columns\IconColumn::make('priority')
                    ->translateLabel()
                    ->extraHeaderAttributes(['class' => 'column-header-no-label'])
                    ->sortable()
                    ->icon(fn($state) => PriorityEnum::from($state)->getIcon())
                    ->color(fn($state) => PriorityEnum::from($state)->getColor())
                    ->tooltip(fn($state) => PriorityEnum::from($state)->getLabel())
                    ->toggleable(),
                Tables\Columns\IconColumn::make('work_session')
                    ->translateLabel()
                    ->wrapHeader()
                    ->extraHeaderAttributes(['class' => 'column-header-no-label'])
                    ->boolean()
                    ->getStateUsing(fn($record) => $record->workSessions()->where('is_running', true)->count())
                    ->falseColor(Color::hex('#ddd'))
                    ->toggleable(),
                Tables\Columns\SelectColumn::make('status_id')
                    ->label('Status')
                    ->options(fn(): array => Status::all()->pluck('name', 'id')->toArray())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\ColorColumn::make('status.color')
                    ->label('Status Color')
                    ->extraHeaderAttributes(['class' => 'column-header-no-label'])
                    ->tooltip(fn(Model $record) => $record->status->name)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('title')
                    ->translateLabel()
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->translateLabel()
                    ->wrapHeader()
                    ->date(PejotaHelper::getUserDateFormat())
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('effort')
                    ->translateLabel()
                    ->formatStateUsing(fn(Model $record): string => $record->effort . ' ' . $record->effort_unit)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('planned_start')
                    ->translateLabel()
                    ->wrapHeader()
                    ->date(PejotaHelper::getUserDateFormat())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('planned_end')
                    ->translateLabel()
                    ->wrapHeader()
                    ->date(PejotaHelper::getUserDateFormat())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('client.labelName')
                    ->translateLabel()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->translateLabel()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\SpatieTagsColumn::make('tags')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filtersFormColumns(4)
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->relationship('status', 'name')
                    ->multiple(true)
                    ->preload(),
                Tables\Filters\SelectFilter::make('client')
                    ->translateLabel()
                    ->relationship('client', 'name'),
                Tables\Filters\Filter::make('due_date_not_empty')
                    ->form([
                        Forms\Components\ToggleButtons::make('due_date')
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
                                fn(Builder $query): Builder => $query->whereNotNull('due_date')
                            )
                            ->when(
                                $data['due_date'] == 'empty',
                                fn(Builder $query): Builder => $query->whereNull('due_date')
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['due_date']) {
                            return $data['due_date'] == 'not_empty' ? __('Has due date') : __('No due date');
                        }

                        return null;
                    }),
                Tables\Filters\Filter::make('due_date')
                    ->columnSpan(2)
                    ->form([
                        Forms\Components\DatePicker::make('from_due_date')
                            ->translateLabel()
                            ->inlineLabel(),
                        Forms\Components\DatePicker::make('to_due_date')
                            ->translateLabel()
                            ->inlineLabel(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_due_date'],
                                fn(Builder $query, $date): Builder => $query->where('due_date', '>=', $data['from_due_date'])
                            )
                            ->when(
                                $data['to_due_date'],
                                fn(Builder $query, $date): Builder => $query->where('due_date', '<=', $data['to_due_date'])
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['from_due_date'] || $data['to_due_date']) {
                            return __('Due date') . ': ' . $data['from_due_date'] . ' - ' . $data['to_due_date'];
                        }

                        return null;
                    }),
                Tables\Filters\Filter::make('planned_end')
                    ->columnSpan(2)
                    ->form([
                        Forms\Components\DatePicker::make('from_planned_end')
                            ->translateLabel()
                            ->inlineLabel(),
                        Forms\Components\DatePicker::make('to_planned_end')
                            ->translateLabel()
                            ->inlineLabel(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_planned_end'],
                                fn(Builder $query, $date): Builder => $query->where(DB::raw('DATE(planned_end'), '>=', $data['from_planned_end'])
                            )
                            ->when(
                                $data['to_planned_end'],
                                fn(Builder $query, $date): Builder => $query->where(DB::raw('DATE(planned_end)'), '<=', $data['to_planned_end'])
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['from_planned_end'] || $data['to_planned_end']) {
                            return __('Due date') . ': ' . $data['from_planned_end'] . ' - ' . $data['to_planned_end'];
                        }

                        return null;
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    CommentsAction::make(),
                    Tables\Actions\Action::make(__('Start Session'))
                        ->tooltip(__('Start a new session for this task'))
                        ->icon('heroicon-o-play')
                        ->color(Color::Amber)
                        ->url(fn($record) => CreateWorkSession::getUrl([
                            'task' => $record->id,
                        ])),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make(__('Clone'))
                        ->tooltip(__('Clone this record with same details but the dates, then open the form to you fill dates'))
                        ->icon('heroicon-o-document-duplicate')
                        ->color(Color::Amber)
                        ->action(fn(Task $record) => self::clone($record)),

                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkActionGroup::make(
                        self::getPostponeActions('planned_start'),
                    )
                        ->label('Postpone planned start')
                        ->translateLabel()
                        ->icon('heroicon-o-calendar'),

                    Tables\Actions\BulkActionGroup::make(
                        self::getPostponeActions('planned_end'),
                    )
                        ->label('Postpone planned end')
                        ->translateLabel()
                        ->icon('heroicon-o-calendar'),

                    Tables\Actions\BulkActionGroup::make(
                        self::getPostponeActions('due_date'),
                    )
                        ->label('Postpone due date')
                        ->translateLabel()
                        ->icon('heroicon-o-calendar'),

                    Tables\Actions\BulkAction::make(__('Clone selected'))
                        ->tooltip(__('Clone this session with same time and details, updating to current date'))
                        ->icon('heroicon-o-document-duplicate')
                        ->color(Color::Amber)
                        ->action(fn(\Illuminate\Support\Collection $records) => self::cloneCollection($records))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                ])
            ])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->persistColumnSearchesInSession();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->name('Task')
            ->schema([
                Split::make([
                    Grid::make(1)->schema([
                        Section::make([
                            SpatieTagsEntry::make('tags')
                                ->label('')
                                ->icon('heroicon-o-tag'),

                            Grid::make(2)->schema([
                                TextEntry::make('project.name')
                                    ->label('')
                                    ->icon('heroicon-o-presentation-chart-bar')
                                    ->hidden(fn($state) => !$state)
                                    ->url(fn($record) => ViewProject::getUrl([$record->project_id])),

                                TextEntry::make('client.name')
                                    ->label('')
                                    ->icon('heroicon-o-building-office')
                                    ->hidden(fn($state) => !$state)
                                    ->url(fn($record) => ViewClient::getUrl([$record->client_id])),

                                TextEntry::make('parent.title')
                                    ->label('')
                                    ->icon(TaskResource::getNavigationIcon())
                                    ->hidden(fn($state) => !$state)
                                    ->url(fn($record) => Pages\ViewTask::getUrl([$record->parent_id]))
                            ]),

                            TextEntry::make('description')
                                ->translateLabel()
                                ->formatStateUsing(fn(string $state): HtmlString => new HtmlString($state))
                                ->icon('heroicon-o-document-text'),

                            Grid::make(4)->schema([
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
                            Tabs\Tab::make('Checklist')
                                ->badge(fn(Model $record): int => $record->checklist ? count($record->checklist) : 0)
                                ->schema([
                                    RepeatableEntry::make('checklist')
                                        ->contained(false)
                                        ->hiddenLabel()
                                        ->schema([
                                            TextEntry::make('item')
                                                ->hiddenLabel()
                                                ->formatStateUsing(function ($state, $component): HtmlString {
                                                    if (self::getStateCompleted($component)) {
                                                        $state = '<s>' . $state . '</s>';
                                                    }

                                                    return new HtmlString($state);
                                                })
                                                ->prefixAction(fn($component) => Action::make('checkCompleted')
                                                    ->icon(self::getStateCompleted($component) ? 'heroicon-o-check' : 'heroicon-o-stop')
                                                    ->color(self::getStateCompleted($component) ? Color::Green : Color::Gray)
                                                    ->action(function (Model $record, $component) {
                                                        $index = explode('.', $component->getStatePath())[1];
                                                        $checklist = $record->checklist;
                                                        $checklist[$index]['completed'] = !$record->checklist[$index]['completed'];
                                                        $record->checklist = $checklist;
                                                        $record->save();
                                                    })
                                                ),
                                        ]),
                                ]),

                            Tabs\Tab::make('Subtasks')
                                ->translateLabel()
                                ->badge(fn(Model $record): int => $record->children->count())
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
                                                    ->hiddenLabel(fn($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->icon(fn($state) => PriorityEnum::from($state)->getIcon())
                                                    ->color(fn($state) => PriorityEnum::from($state)->getColor())
                                                    ->tooltip(fn($state) => PriorityEnum::from($state)->getLabel()),

                                                TextEntry::make('status.name')
                                                    ->hiddenLabel(fn($record) => $record->sort != 0)
                                                    ->badge()
                                                    ->color(fn(Model $record): array => Color::hex($record->status->color)),
                                                TextEntry::make('title')
                                                    ->hiddenLabel(fn($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->columnSpan(8)
                                                    ->action(
                                                        Action::make('view')
                                                            ->infolist(fn(Model $record) => self::infolist(
                                                                (new Infolist())->record($record)
                                                            ))
                                                            ->modalWidth(MaxWidth::FitContent)
                                                            ->modalCancelAction(false)
                                                            ->modalSubmitActionLabel('Close')
                                                    ),
                                                TextEntry::make('due_date')
                                                    ->hiddenLabel(fn($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->date(PejotaHelper::getUserDateFormat())
                                                    ->icon('heroicon-o-calendar')
                                                    ->columnSpan(2),
                                            ]),
                                        ]),
                                ]),
                        ]),

                        Tabs::make('Tabs')->schema([
                            Tabs\Tab::make('Comments')
                                ->translateLabel()
                                ->badge(fn(Model $record): int => $record->filamentComments->count())
                                ->schema([
                                    CommentsEntry::make('filament_comments')
                                        ->columnSpanFull(),
                                ]),

                            Tabs\Tab::make('Sessions')
                                ->translateLabel()
                                ->badge(fn(Model $record): int => $record->workSessions->count())
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
                                                    ->hiddenLabel(fn($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->dateTime(PejotaHelper::getUserDateTimeFormat())
                                                    ->url(
                                                        fn($record) => ViewWorkSession::getUrl([
                                                            'record' => $record->id,
                                                        ])
                                                    ),

                                                TextEntry::make('duration')
                                                    ->hiddenLabel(fn($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->icon(fn(Model $record): string => $record->is_running ? '' : 'heroicon-o-clock')
                                                    ->formatStateUsing(fn($state): string => PejotaHelper::formatDuration($state))
                                                    ->hidden(fn($record) => $record->is_running),

                                                Actions::make([
                                                    Action::make('stop')
                                                        ->translateLabel()
                                                        ->icon('heroicon-o-stop')
                                                        ->color(Color::Red)
                                                        ->requiresConfirmation()
                                                        ->action(function (WorkSession $record) {
                                                            unset($record->sort);
                                                            return $record->finish();
                                                        })
                                                ])
                                                    ->hidden(fn($record) => !$record->is_running),

                                                TextEntry::make('title')
                                                    ->hiddenLabel(fn($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->columnSpan(fn(Model $record): int => $record->description ? 2 : 4),
                                                TextEntry::make('description')
                                                    ->hiddenLabel(fn($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->html()
                                                    ->columnSpan(2)
                                                    ->visible(fn($state) => !$state),
                                            ]),
                                        ]),
                                ]),

                            Tabs\Tab::make('History')
                                ->translateLabel()
                                ->badge(fn(Model $record): int => $record->activities->count())
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
                                                        fn(Model $record): array => [$record->properties->get('attributes')['status.name']]
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
                        Grid::make(2)->schema([
                            IconEntry::make('priority')
                                ->translateLabel()
                                ->icon(fn($state) => PriorityEnum::from($state)->getIcon())
                                ->color(fn($state) => PriorityEnum::from($state)->getColor())
                                ->tooltip(fn($state) => PriorityEnum::from($state)->getLabel()),

                            TextEntry::make('status.name')
                                ->badge()
                                ->color(fn(Model $record): array => Color::hex($record->status->color))
                                ->hintActions([
                                    Action::make('change_status')
                                        ->hiddenLabel()
                                        ->icon('heroicon-o-pencil')
                                        ->color(Color::hex('#ACA'))
                                        ->button()
                                        ->tooltip(__('Change status'))
                                        ->action(function (Model $record, array $data): void {
                                            $record->update($data);
                                        })
                                        ->form([
                                            Forms\Components\Select::make('status_id')
                                                ->label('Status')
                                                ->options(fn(): array => Status::all()->pluck('name', 'id')->toArray())
                                        ]),
                                ]),
                        ]),

                        TextEntry::make('due_date')
                            ->translateLabel()
                            ->date(PejotaHelper::getUserDateFormat())
                            ->icon('heroicon-o-exclamation-triangle'),

                        TextEntry::make('effort')
                            ->translateLabel()
                            ->label('Estimated')
                            ->inlineLabel()
                            ->icon('heroicon-o-variable')
                            ->formatStateUsing(fn(Model $record): string => $record->effort . ' ' . $record->effort_unit),

                        TextEntry::make('workSessions')
                            ->label("Sessions")
                            ->translateLabel()
                            ->inlineLabel()
                            ->icon('heroicon-o-clock')
                            ->formatStateUsing(fn(Model $record): string => PejotaHelper::formatDuration($record->workSessions->sum('duration'))),

                        Actions::make([
                            Action::make('list')
                                ->translateLabel()
                                ->url(
                                    fn(Model $record) => './.'
                                )
                                ->icon('heroicon-o-chevron-left')
                                ->color(Color::Neutral),

                            Action::make('edit')
                                ->translateLabel()
                                ->url(
                                    fn(Model $record) => "{$record->id}/edit"
                                )
                                ->icon('heroicon-o-pencil'),

                            Action::make('subtask')
                                ->translateLabel()
                                ->icon(TaskResource::getNavigationIcon())
                                ->color(Color::Green)
                                ->modal(true)
                                ->url(fn($record) => Pages\CreateTask::getUrl([
                                    'parent' => $record->id,
                                ])),

                            Action::make('session')
                                ->translateLabel()
                                ->icon(WorkSessionResource::getNavigationIcon())
                                ->color(Color::Amber)
                                ->modal(true)
                                ->url(fn($record) => CreateWorkSession::getUrl([
                                    'task' => $record->id,
                                ])),
                        ]),
                    ])
                        ->grow(false), // Section at right
                ])
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
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'view' => Pages\ViewTask::route('/{record}'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
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
            Tables\Actions\BulkAction::make($field . '_postpone_today')
                ->label('Today')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn(Collection $records) => $records->each->postpone($field, 'today')),
            Tables\Actions\BulkAction::make($field . '_postpone_1_day')
                ->label('1 day')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn(Collection $records) => $records->each->postpone($field, '1 day')),
            Tables\Actions\BulkAction::make($field . '_postpone_3_days')
                ->label('3 days')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn(Collection $records) => $records->each->postpone($field, '3 days')),
            Tables\Actions\BulkAction::make($field . '_postpone_5_days')
                ->label('5 days')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn(Collection $records) => $records->each->postpone($field, '5 days')),
            Tables\Actions\BulkAction::make($field . '_postpone_1_week')
                ->label('1 week')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn(Collection $records) => $records->each->postpone($field, '1 week')),
            Tables\Actions\BulkAction::make($field . '_postpone_2_weeks')
                ->label('2 weeks')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn(Collection $records) => $records->each->postpone($field, '2 weeks')),
            Tables\Actions\BulkAction::make($field . '_postpone_1_month')
                ->label('1 month')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->action(fn(Collection $records) => $records->each->postpone($field, '1 month')),
            Tables\Actions\BulkAction::make($field . '_postpone_custom')
                ->label('Custom')
                ->translateLabel()
                ->deselectRecordsAfterCompletion()
                ->form([
                    Forms\Components\DatePicker::make($field)
                        ->translateLabel()
                        ->required()
                ])
                ->action(function ($data, Collection $records) use ($field) {
                    foreach ($records as $record) {
                        $record->{$field} = $data[$field];
                        $record->save();
                    }
                }),

        ];
    }

    public static function cloneCollection(\Illuminate\Support\Collection $records)
    {
        $records->each(fn($record) => self::clone($record));
    }

    public static function clone(Task $record)
    {
        $newModel = $record->replicate();
        $newModel->due_date = null;
        $newModel->planned_end = null;
        $newModel->planned_start = null;
        $newModel->actual_end = null;
        $newModel->actual_start = null;
        $newModel->status_id = Status::select('id')->orderBy('order')->first()?->id;
        $newModel->save();

        return redirect(Pages\EditTask::getUrl([$newModel->id]));
    }
}
