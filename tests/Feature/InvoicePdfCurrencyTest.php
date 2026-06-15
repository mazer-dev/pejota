<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Number;
use Tests\TestCase;

class InvoicePdfCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_renders_amounts_in_document_currency(): void
    {
        $user = User::factory()->create();
        $user->company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');
        $this->actingAs($user);

        $client = Client::create(['name' => 'ACME', 'company_id' => $user->company->id]);
        $unit = Unit::create(['name' => 'h', 'symbol' => 'h', 'company_id' => $user->company->id]);
        $product = Product::create([
            'name' => 'Dev',
            'service' => true,
            'digital' => false,
            'unit_id' => $unit->id,
            'company_id' => $user->company->id,
        ]);

        $invoice = Invoice::create([
            'number' => 'INV-1', 'title' => 'Services', 'status' => InvoiceStatusEnum::SENT,
            'client_id' => $client->id, 'currency' => 'USD', 'total' => 250.00,
            'company_id' => $user->company->id,
        ]);
        $invoice->items()->create([
            'product_id' => $product->id, 'name' => 'Dev', 'unit_id' => $unit->id,
            'quantity' => 1, 'price' => 250.00, 'total' => 250.00,
        ]);

        $html = Blade::render('invoice.pdf', ['invoice' => $invoice->fresh('items')]);

        $expected = Number::currency(250.00, 'USD', PejotaHelper::getUserLocate());
        $this->assertStringContainsString($expected, $html);
    }
}
