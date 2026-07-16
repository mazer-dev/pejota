<?php

namespace App\Filament\App\Resources\WorkSessionResource\Pages;

use App\Enums\CompanySettingsEnum;
use App\Enums\TimesheetDetailLevel;
use App\Enums\TimesheetGrouping;
use App\Filament\App\Resources\InvoiceResource;
use App\Filament\App\Resources\WorkSessionResource;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Product;
use App\Models\Unit;
use App\Models\WorkSession;
use App\Services\Invoicing\SessionInvoiceRequest;
use App\Services\Invoicing\SessionInvoiceService;
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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportRedirects\Redirector;
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
                ->schema([
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
            Action::make('generateInvoice')
                ->label(__('Generate invoice'))
                ->icon('heroicon-o-document-text')
                ->fillForm(function (): array {
                    $tz = PejotaHelper::getUserTimeZone() ?? 'UTC';
                    $from = CarbonImmutable::now($tz)->startOfMonth()->format('Y-m-d');
                    $to = CarbonImmutable::now($tz)->endOfMonth()->format('Y-m-d');

                    return [
                        'client_id' => $this->tableFilters['client']['value'] ?? null,
                        'from' => $from,
                        'to' => $to,
                        'grouping' => TimesheetGrouping::None->value,
                        'product_id' => PejotaHelper::currentCompany()->settings()->get(CompanySettingsEnum::INVOICE_SESSION_PRODUCT->value),
                        'unit_id' => PejotaHelper::currentCompany()->settings()->get(CompanySettingsEnum::INVOICE_SESSION_UNIT->value),
                        'title' => __('Work sessions :from–:to', ['from' => $from, 'to' => $to]),
                    ];
                })
                ->schema([
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
                    Select::make('product_id')
                        ->label(__('Product'))
                        ->options(fn (): array => Product::orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required(),
                    Select::make('unit_id')
                        ->label(__('Unit'))
                        ->options(fn (): array => Unit::orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('title')->label(__('Title'))->required(),
                ])
                ->action(function (array $data): ?Redirector {
                    $tz = PejotaHelper::getUserTimeZone() ?? 'UTC';

                    $request = new SessionInvoiceRequest(
                        clientId: (int) $data['client_id'],
                        from: CarbonImmutable::parse($data['from'], $tz)->startOfDay()->setTimezone('UTC'),
                        to: CarbonImmutable::parse($data['to'], $tz)->endOfDay()->setTimezone('UTC'),
                        timezone: $tz,
                        grouping: TimesheetGrouping::from($data['grouping']),
                        productId: (int) $data['product_id'],
                        unitId: (int) $data['unit_id'],
                    );

                    PejotaHelper::currentCompany()->settings()->set(CompanySettingsEnum::INVOICE_SESSION_PRODUCT->value, (int) $data['product_id']);
                    PejotaHelper::currentCompany()->settings()->set(CompanySettingsEnum::INVOICE_SESSION_UNIT->value, (int) $data['unit_id']);

                    $invoice = app(SessionInvoiceService::class)->createInvoice($request, ['title' => $data['title']]);

                    if ($invoice === null) {
                        Notification::make()->warning()->title(__('No billable sessions to invoice.'))->send();

                        return null;
                    }

                    Notification::make()->success()->title(__('Invoice created.'))->send();

                    return redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'running' => Tab::make()
                ->label(__('Running'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_running', true))
                ->badge(fn (): int => WorkSession::where('is_running', true)->count())
                ->badgeColor(Color::Green),
            'all' => Tab::make()
                ->label(__('All')),
        ];
    }
}
