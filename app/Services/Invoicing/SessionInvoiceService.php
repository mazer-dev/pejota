<?php

namespace App\Services\Invoicing;

use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\TimesheetGrouping;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\WorkSession;
use App\Services\Timesheet\SessionGroupKey;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SessionInvoiceService
{
    /**
     * @return Collection<int, DraftInvoiceItem>
     */
    public function buildDraftItems(SessionInvoiceRequest $request): Collection
    {
        $sessions = WorkSession::query()
            ->billableOpen()
            ->where('client_id', $request->clientId)
            ->whereBetween('start', [$request->from, $request->to])
            ->with(['task', 'project', 'client'])
            ->orderBy('start')
            ->get();

        return $sessions
            ->groupBy(fn (WorkSession $session) => SessionGroupKey::for($session, $request->grouping, $request->timezone))
            ->map(function (Collection $group, string $label) use ($request) {
                $minutes = (int) $group->sum('duration');
                $value = round((float) $group->sum(fn (WorkSession $s) => (float) $s->value), 2);
                $rates = $group->map(fn (WorkSession $s) => (float) $s->rate)->unique();
                $uniform = $rates->count() === 1;

                return new DraftInvoiceItem(
                    name: $request->grouping === TimesheetGrouping::None
                        ? __('Services :from–:to', [
                            'from' => $request->from->setTimezone($request->timezone)->format('Y-m-d'),
                            'to' => $request->to->setTimezone($request->timezone)->format('Y-m-d'),
                        ])
                        : $label,
                    quantity: $uniform ? round($minutes / 60, 2) : 1.0,
                    price: $uniform ? (float) $rates->first() : $value,
                    total: $value, // C1: always Σ session.value, reconciles with the frozen sessions
                    sessionIds: $group->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    uniformRate: $uniform,
                );
            })
            ->values();
    }

    /**
     * @param  array{title: string}  $meta
     */
    public function createInvoice(SessionInvoiceRequest $request, array $meta): ?Invoice
    {
        $items = $this->buildDraftItems($request);

        if ($items->isEmpty()) {
            return null;
        }

        $client = Client::findOrFail($request->clientId);
        $currency = $client->currency ?? PejotaHelper::getUserCurrency();

        return DB::transaction(function () use ($items, $client, $currency, $meta, $request): Invoice {
            $invoice = Invoice::create([
                'number' => CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumberFormated(),
                'title' => $meta['title'],
                'client_id' => $client->id,
                'currency' => $currency,
                'status' => InvoiceStatusEnum::DRAFT,
                'total' => round($items->sum(fn (DraftInvoiceItem $i) => $i->total), 2),
            ]);

            $this->persistItems($invoice, $items, $request);

            return $invoice;
        });
    }

    public function appendToInvoice(Invoice $invoice, SessionInvoiceRequest $request): int
    {
        $client = Client::findOrFail($request->clientId);
        $base = PejotaHelper::getUserCurrency();

        if (($invoice->currency ?? $base) !== ($client->currency ?? $base)) {
            throw new DomainException(__('The invoice currency does not match the client currency.'));
        }

        $items = $this->buildDraftItems($request);

        if ($items->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($invoice, $items, $request): int {
            $this->persistItems($invoice, $items, $request);

            $total = $invoice->items()->get()->sum(fn (InvoiceItem $i) => (float) $i->total);
            $invoice->total = round($total - (float) ($invoice->discount ?? 0), 2);
            $invoice->save();

            return $items->count();
        });
    }

    /**
     * @param  Collection<int, DraftInvoiceItem>  $items
     */
    private function persistItems(Invoice $invoice, Collection $items, SessionInvoiceRequest $request): void
    {
        foreach ($items as $item) {
            $invoiceItem = $invoice->items()->create([
                'product_id' => $request->productId,
                'unit_id' => $request->unitId,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->total,
            ]);

            // Mass update is deliberate: sets the FK WITHOUT firing WorkSession::recalculate(),
            // preserving the already-frozen value. Tenant scope still applies.
            WorkSession::whereIn('id', $item->sessionIds)->update(['invoice_item_id' => $invoiceItem->id]);
        }
    }
}
