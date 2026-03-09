<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloneInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_clone_page_loads_with_source_invoice_data(): void
    {
        $client = Client::create([
            'name' => 'Test Client',
            'company_id' => $this->user->company->id,
        ]);

        $unit = Unit::create([
            'name' => 'Hour',
            'symbol' => 'h',
            'company_id' => $this->user->company->id,
        ]);

        $product = Product::create([
            'name' => 'Dev Service',
            'service' => true,
            'digital' => false,
            'price' => 100.00,
            'unit_id' => $unit->id,
            'company_id' => $this->user->company->id,
        ]);

        $invoice = Invoice::create([
            'number' => 'TEST-001',
            'title' => 'Original Invoice',
            'status' => InvoiceStatusEnum::PAID,
            'client_id' => $client->id,
            'due_date' => '2026-01-15',
            'payment_date' => '2026-01-20',
            'extra_info' => 'Some extra info',
            'obs_internal' => 'Internal notes',
            'discount' => 10.00,
            'total' => 190.00,
            'company_id' => $this->user->company->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'name' => 'Development Work',
            'unit_id' => $unit->id,
            'quantity' => 2,
            'price' => 100.00,
            'discount' => 0,
            'total' => 200.00,
        ]);

        $response = $this->get(route('filament.app.resources.invoices.create', ['clone' => $invoice->id]));
        $response->assertOk();
        $response->assertSee('Original Invoice');
        $response->assertSee('Some extra info');
        $response->assertSee('Internal notes');
    }

    public function test_clone_page_loads_normally_without_clone_parameter(): void
    {
        $this->get(route('filament.app.resources.invoices.create'))
            ->assertOk();
    }
}
