<?php

namespace App\Services\Messaging;

use App\Helpers\PejotaHelper;
use App\Models\Invoice;
use Illuminate\Support\Number;

class TemplateContextBuilder
{
    /**
     * @return array<string, string>
     */
    public function forInvoice(Invoice $invoice): array
    {
        $client = $invoice->client;
        $company = $invoice->company;
        $currency = $invoice->currency ?? PejotaHelper::getUserCurrency();

        return [
            'invoice.number' => (string) $invoice->number,
            'invoice.title' => (string) $invoice->title,
            'invoice.currency' => (string) $currency,
            'invoice.total' => (string) Number::currency((float) $invoice->total, $currency, PejotaHelper::getUserLocate()),
            'invoice.due_date' => $invoice->due_date?->format(PejotaHelper::getUserDateFormat()) ?? '',
            'invoice.due_month' => $invoice->due_date?->translatedFormat('M/Y') ?? '',
            'client.name' => (string) ($client?->name ?? ''),
            'client.tradename' => (string) ($client?->tradename ?? ''),
            'company.name' => (string) ($company?->name ?? ''),
            'user.name' => (string) (auth()->user()?->name ?? ''),
        ];
    }
}
