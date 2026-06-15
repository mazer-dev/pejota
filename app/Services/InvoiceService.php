<?php

namespace App\Services;

use App\Enums\CompanySettingsEnum;
use App\Exceptions\MissingExchangeRateException;
use App\Models\Company;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function backfillNullCurrencies(): void
    {
        foreach (Company::all() as $company) {
            $base = $company->settings()->get(CompanySettingsEnum::FINANCE_CURRENCY->value) ?? 'USD';

            DB::table('invoices')
                ->where('company_id', $company->id)
                ->whereNull('currency')
                ->update(['currency' => $base]);
        }
    }

    /**
     * Sum baseTotal (already in monetary units) across invoices in the base currency.
     * MissingExchangeRateException per invoice is swallowed: that invoice is excluded and counted.
     *
     * @param  iterable<Invoice>  $invoices
     * @return array{total: float, unconverted: int}
     */
    public function sumBaseCurrency(iterable $invoices): array
    {
        $total = 0.0;
        $unconverted = 0;

        foreach ($invoices as $invoice) {
            try {
                $total += $invoice->baseTotal;
            } catch (MissingExchangeRateException) {
                $unconverted++;
            }
        }

        return ['total' => $total, 'unconverted' => $unconverted];
    }

    public function generatePdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadHtml(
            Blade::render('invoice.pdf', ['invoice' => $invoice])
        );
    }
}
