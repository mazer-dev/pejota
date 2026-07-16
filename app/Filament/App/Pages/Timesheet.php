<?php

namespace App\Filament\App\Pages;

use App\Enums\MenuGroupsEnum;
use App\Enums\TimesheetDetailLevel;
use App\Enums\TimesheetGrouping;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Services\Timesheet\Renderers\CsvTimesheetRenderer;
use App\Services\Timesheet\Renderers\PdfTimesheetRenderer;
use App\Services\Timesheet\TimesheetBuilder;
use App\Services\Timesheet\TimesheetData;
use App\Services\Timesheet\TimesheetLayoutRegistry;
use App\Services\Timesheet\TimesheetRequest;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Timesheet extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected string $view = 'filament.app.pages.timesheet';

    public ?array $data = [];

    public bool $hasPreview = false;

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::FINANCE->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('Timesheet');
    }

    public function mount(): void
    {
        $this->form->fill([
            'from' => CarbonImmutable::now(PejotaHelper::getUserTimeZone())->startOfMonth()->format('Y-m-d'),
            'to' => CarbonImmutable::now(PejotaHelper::getUserTimeZone())->endOfMonth()->format('Y-m-d'),
            'grouping' => TimesheetGrouping::None->value,
            'detailLevel' => TimesheetDetailLevel::Detailed->value,
            'includeValue' => true,
            'billableOnly' => false,
            'layoutKey' => 'client',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(4)
                    ->schema([
                        Select::make('client_id')
                            ->label(__('Client'))
                            ->options(fn (): array => Client::orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required()
                            ->columnSpan(2),
                        DatePicker::make('from')->label(__('From'))->required()->columnSpan(1),
                        DatePicker::make('to')->label(__('To'))->required()->afterOrEqual('from')->columnSpan(1),
                    ]),
                Grid::make(3)
                    ->schema([
                        Select::make('grouping')
                            ->label(__('Grouping'))
                            ->options([
                                TimesheetGrouping::None->value => __('None'),
                                TimesheetGrouping::Project->value => __('Project'),
                                TimesheetGrouping::Task->value => __('Task'),
                                TimesheetGrouping::Day->value => __('Day'),
                                TimesheetGrouping::Week->value => __('Week'),
                                TimesheetGrouping::Month->value => __('Month'),
                            ])
                            ->required(),
                        Select::make('detailLevel')
                            ->label(__('Detail level'))
                            ->options([
                                TimesheetDetailLevel::Detailed->value => __('Detailed (per session)'),
                                TimesheetDetailLevel::GroupSummary->value => __('Summary per group'),
                                TimesheetDetailLevel::ParentTaskRollup->value => __('Roll up subtasks into parent'),
                            ])
                            ->required(),
                        Select::make('layoutKey')
                            ->label(__('Layout'))
                            ->options(fn (): array => collect(app(TimesheetLayoutRegistry::class)->all())
                                ->mapWithKeys(fn ($layout, $key) => [$key => $layout->label()])->all())
                            ->required(),
                    ]),
                Toggle::make('includeValue')->label(__('Include value')),
                Toggle::make('billableOnly')->label(__('Billable only')),
            ])
            ->statePath('data')
            ->columns(2);
    }

    private function buildRequest(): TimesheetRequest
    {
        $state = $this->data;
        $tz = PejotaHelper::getUserTimeZone() ?? 'UTC';
        $client = Client::find($state['client_id']);

        return new TimesheetRequest(
            clientId: (int) $state['client_id'],
            from: CarbonImmutable::parse($state['from'], $tz)->startOfDay()->setTimezone('UTC'),
            to: CarbonImmutable::parse($state['to'], $tz)->endOfDay()->setTimezone('UTC'),
            timezone: $tz,
            currency: $client?->currency ?? PejotaHelper::getUserCurrency(),
            grouping: TimesheetGrouping::from($state['grouping']),
            detailLevel: TimesheetDetailLevel::from($state['detailLevel']),
            includeValue: (bool) $state['includeValue'],
            billableOnly: (bool) $state['billableOnly'],
            layoutKey: $state['layoutKey'],
        );
    }

    public function preview(): void
    {
        $this->form->getState();
        $this->hasPreview = true;
        unset($this->previewData);
    }

    #[Computed]
    public function previewData(): ?TimesheetData
    {
        if (! $this->hasPreview || blank($this->data['client_id'] ?? null)) {
            return null;
        }

        return app(TimesheetBuilder::class)->build($this->buildRequest());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label(__('Export PDF'))
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $this->form->getState();
                    $request = $this->buildRequest();
                    $data = app(TimesheetBuilder::class)->build($request);
                    $layout = app(TimesheetLayoutRegistry::class)->get($request->layoutKey);

                    return app(PdfTimesheetRenderer::class)->download($data, $layout, $request);
                }),
            Action::make('exportCsv')
                ->label(__('Export CSV'))
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $this->form->getState();
                    $request = $this->buildRequest();
                    $data = app(TimesheetBuilder::class)->build($request);
                    $layout = app(TimesheetLayoutRegistry::class)->get($request->layoutKey);

                    return app(CsvTimesheetRenderer::class)->render($data, $layout, $request);
                }),
        ];
    }
}
