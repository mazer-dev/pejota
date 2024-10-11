<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Blade;

class InvoiceService
{
    public function generatePdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadHtml(
            Blade::render('invoice.pdf', ['invoice' => $invoice])
        );
    }
}
