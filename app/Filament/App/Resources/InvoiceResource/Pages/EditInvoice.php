<?php

namespace App\Filament\App\Resources\InvoiceResource\Pages;

use App\Enums\CompanySettingsEnum;
use App\Enums\TimesheetGrouping;
use App\Filament\App\Resources\InvoiceResource;
use App\Helpers\PejotaHelper;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Invoicing\SessionInvoiceRequest;
use App\Services\Invoicing\SessionInvoiceService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Features\SupportRedirects\Redirector;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            Action::make('addSessions')
                ->label(__('Add items from work sessions'))
                ->icon('heroicon-o-clock')
                ->modalDescription(__('Unsaved manual edits in the items list are discarded when sessions are added — save first.'))
                ->fillForm(fn (): array => [
                    'from' => CarbonImmutable::now(PejotaHelper::getUserTimeZone() ?? 'UTC')->startOfMonth()->format('Y-m-d'),
                    'to' => CarbonImmutable::now(PejotaHelper::getUserTimeZone() ?? 'UTC')->endOfMonth()->format('Y-m-d'),
                    'grouping' => TimesheetGrouping::None->value,
                    'product_id' => PejotaHelper::currentCompany()->settings()->get(CompanySettingsEnum::INVOICE_SESSION_PRODUCT->value),
                    'unit_id' => PejotaHelper::currentCompany()->settings()->get(CompanySettingsEnum::INVOICE_SESSION_UNIT->value),
                ])
                ->form([
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
                ])
                ->action(function (array $data): ?Redirector {
                    $tz = PejotaHelper::getUserTimeZone() ?? 'UTC';

                    $request = new SessionInvoiceRequest(
                        clientId: (int) $this->record->client_id,
                        from: CarbonImmutable::parse($data['from'], $tz)->startOfDay()->setTimezone('UTC'),
                        to: CarbonImmutable::parse($data['to'], $tz)->endOfDay()->setTimezone('UTC'),
                        timezone: $tz,
                        grouping: TimesheetGrouping::from($data['grouping']),
                        productId: (int) $data['product_id'],
                        unitId: (int) $data['unit_id'],
                    );

                    try {
                        $count = app(SessionInvoiceService::class)->appendToInvoice($this->record, $request);
                    } catch (\DomainException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return null;
                    }

                    if ($count === 0) {
                        Notification::make()->warning()->title(__('No billable sessions to invoice.'))->send();

                        return null;
                    }

                    Notification::make()->success()->title(__(':count item(s) added.', ['count' => $count]))->send();

                    return redirect(InvoiceResource::getUrl('edit', ['record' => $this->record]));
                }),
        ];
    }
}
