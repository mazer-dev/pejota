<?php

namespace App\Filament\App\Resources\WorkSessionResource\Pages;

use App\Enums\TimesheetDetailLevel;
use App\Enums\TimesheetGrouping;
use App\Filament\App\Resources\WorkSessionResource;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\WorkSession;
use App\Services\Timesheet\Renderers\CsvTimesheetRenderer;
use App\Services\Timesheet\Renderers\PdfTimesheetRenderer;
use App\Services\Timesheet\TimesheetBuilder;
use App\Services\Timesheet\TimesheetLayoutRegistry;
use App\Services\Timesheet\TimesheetRequest;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListWorkSessions extends ListRecords
{
    protected static string $resource = WorkSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('generateTimesheet')
                ->label(__('Generate timesheet'))
                ->icon('heroicon-o-document-chart-bar')
                ->fillForm(fn (): array => [
                    'client_id' => $this->tableFilters['client']['value'] ?? null,
                    'from' => CarbonImmutable::now(PejotaHelper::getUserTimeZone() ?? 'UTC')->startOfMonth()->format('Y-m-d'),
                    'to' => CarbonImmutable::now(PejotaHelper::getUserTimeZone() ?? 'UTC')->endOfMonth()->format('Y-m-d'),
                    'grouping' => TimesheetGrouping::None->value,
                    'detailLevel' => TimesheetDetailLevel::Detailed->value,
                    'layoutKey' => 'client',
                    'format' => 'pdf',
                    'includeValue' => true,
                    'billableOnly' => false,
                ])
                ->form([
                    Select::make('client_id')
                        ->label(__('Client'))
                        ->options(fn (): array => Client::orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required(),
                    DatePicker::make('from')->label(__('From'))->required(),
                    DatePicker::make('to')->label(__('To'))->required()->afterOrEqual('from'),
                    Select::make('grouping')
                        ->label(__('Grouping'))
                        ->options([
                            TimesheetGrouping::None->value => __('None'),
                            TimesheetGrouping::Project->value => __('Project'),
                            TimesheetGrouping::Task->value => __('Task'),
                            TimesheetGrouping::Day->value => __('Day'),
                            TimesheetGrouping::Week->value => __('Week'),
                            TimesheetGrouping::Month->value => __('Month'),
                        ])->required(),
                    Select::make('detailLevel')
                        ->label(__('Detail level'))
                        ->options([
                            TimesheetDetailLevel::Detailed->value => __('Detailed (per session)'),
                            TimesheetDetailLevel::GroupSummary->value => __('Summary per group'),
                            TimesheetDetailLevel::ParentTaskRollup->value => __('Roll up subtasks into parent'),
                        ])->required(),
                    Select::make('layoutKey')
                        ->label(__('Layout'))
                        ->options(fn (): array => collect(app(TimesheetLayoutRegistry::class)->all())
                            ->mapWithKeys(fn ($layout, $key) => [$key => $layout->label()])->all())
                        ->required(),
                    Select::make('format')
                        ->label(__('Format'))
                        ->options(['pdf' => 'PDF', 'csv' => 'CSV'])
                        ->required(),
                    Toggle::make('includeValue')->label(__('Include value')),
                    Toggle::make('billableOnly')->label(__('Billable only')),
                ])
                ->action(function (array $data): StreamedResponse {
                    $tz = PejotaHelper::getUserTimeZone() ?? 'UTC';
                    $client = Client::find($data['client_id']);

                    $request = new TimesheetRequest(
                        clientId: (int) $data['client_id'],
                        from: CarbonImmutable::parse($data['from'], $tz)->startOfDay()->setTimezone('UTC'),
                        to: CarbonImmutable::parse($data['to'], $tz)->endOfDay()->setTimezone('UTC'),
                        timezone: $tz,
                        currency: $client?->currency ?? PejotaHelper::getUserCurrency(),
                        grouping: TimesheetGrouping::from($data['grouping']),
                        detailLevel: TimesheetDetailLevel::from($data['detailLevel']),
                        includeValue: (bool) $data['includeValue'],
                        billableOnly: (bool) $data['billableOnly'],
                        layoutKey: $data['layoutKey'],
                    );

                    $timesheet = app(TimesheetBuilder::class)->build($request);
                    $layout = app(TimesheetLayoutRegistry::class)->get($request->layoutKey);

                    return $data['format'] === 'csv'
                        ? app(CsvTimesheetRenderer::class)->render($timesheet, $layout, $request)
                        : app(PdfTimesheetRenderer::class)->download($timesheet, $layout, $request);
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'running' => Tab::make()
                ->label(__('Running'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_running', true))
                ->badge(fn (WorkSession $record): int => $record->where('is_running', true)->count())
                ->badgeColor(Color::Green),
            'all' => Tab::make()
                ->label(__('All')),
        ];
    }
}
