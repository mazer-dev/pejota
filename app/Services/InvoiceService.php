<?php

namespace App\Services;

use App\Enums\CompanySettingsEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class InvoiceService
{
    public function getNextInvoiceNumber(): int
    {
        return CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextNumber();
    }

    public function formatQuotationNumer(string $number): string
    {
        $result = 'ym000';

        if (auth()->user()) {
            $result = auth()->user()->company->settings()
                ->get(
                    CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
                    'ym000'
                );
        }

        $zeros = Str::substrCount($result, '0');

        $datePatterns = ['y', 'Y', 'm', 'M', 'd'];

        foreach ($datePatterns as $pattern) {
            if (Str::contains($result, $pattern)) {
                $replace = Carbon::now()->format($pattern);
                $result = str_replace($pattern, $replace, $result);
            }
        }

        $formatedNumber = str_pad($number, $zeros, '0', STR_PAD_LEFT);

        $result = str_replace(
            str_pad('', $zeros, '0', STR_PAD_LEFT),
            $formatedNumber,
            $result
        );

        return $result;
    }

}
