<?php

namespace Tests\Feature\Invoicing;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class InvoicePdfQuantityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_renders_fractional_quantity(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Landlord::addTenant('company_id', $user->company->id);

        $client = Client::create(['name' => 'Acme', 'company_id' => $user->company->id, 'currency' => 'BRL']);
        $unit = Unit::create(['name' => 'Hour', 'symbol' => 'h', 'company_id' => $user->company->id]);
        $product = Product::create(['name' => 'Consulting', 'symbol' => 'C', 'service' => true, 'digital' => false, 'company_id' => $user->company->id, 'unit_id' => $unit->id, 'price' => 100, 'cost' => 0]);

        $invoice = Invoice::create([
            'number' => 'INV-1', 'title' => 'T', 'client_id' => $client->id, 'currency' => 'BRL', 'total' => 250.00,
        ]);
        $invoice->items()->create([
            'product_id' => $product->id, 'unit_id' => $unit->id, 'name' => 'Work', 'quantity' => 2.50, 'price' => 100.00, 'total' => 250.00,
        ]);

        $output = app(InvoiceService::class)->generatePdf($invoice->fresh('items'))->output();

        $this->assertStringStartsWith('%PDF', $output);
    }
}
