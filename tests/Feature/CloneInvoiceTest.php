<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Filament\App\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class CloneInvoiceTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
    }

    public function test_clone_page_loads_with_source_invoice_data(): void
    {
        $client = Client::create([
            'name' => 'Test Client',
            'company_id' => $this->company->id,
        ]);

        $unit = Unit::create([
            'name' => 'Hour',
            'symbol' => 'h',
            'company_id' => $this->company->id,
        ]);

        $product = Product::create([
            'name' => 'Dev Service',
            'service' => true,
            'digital' => false,
            'price' => 100.00,
            'unit_id' => $unit->id,
            'company_id' => $this->company->id,
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
            'company_id' => $this->company->id,
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

        $response = $this->get(route('filament.app.resources.invoices.create', ['tenant' => $this->company, 'clone' => $invoice->id]));
        $response->assertOk();
        $response->assertSee('Original Invoice');
        $response->assertSee('Some extra info');
        $response->assertSee('Internal notes');
    }

    public function test_clone_page_loads_normally_without_clone_parameter(): void
    {
        $this->get(route('filament.app.resources.invoices.create', ['tenant' => $this->company]))
            ->assertOk();
    }

    public function test_opening_clone_page_does_not_consume_any_invoice_number(): void
    {
        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );

        $client = Client::create([
            'name' => 'Test Client',
            'company_id' => $this->company->id,
        ]);

        $invoice = Invoice::create([
            'number' => 'TEST-001',
            'title' => 'Original Invoice',
            'status' => InvoiceStatusEnum::PAID,
            'client_id' => $client->id,
            'total' => 100.00,
            'company_id' => $this->company->id,
        ]);

        $this->get(route('filament.app.resources.invoices.create', ['tenant' => $this->company, 'clone' => $invoice->id]))
            ->assertOk();

        $lastNumber = $this->company->settings()->get(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->value
        );

        $this->assertNull($lastNumber, 'Opening the clone page must not consume a number');
    }

    public function test_opening_create_page_does_not_consume_any_invoice_number(): void
    {
        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );

        $this->get(route('filament.app.resources.invoices.create', ['tenant' => $this->company]))
            ->assertOk();

        $lastNumber = $this->company->settings()->get(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->value
        );

        $this->assertNull($lastNumber, 'Opening the create page must not consume a number');
    }

    public function test_cloning_preserves_source_currency(): void
    {
        $client = Client::create([
            'name' => 'BRL Client',
            'company_id' => $this->company->id,
            'currency' => 'BRL',
        ]);

        $invoice = Invoice::create([
            'number' => 'BRL-001',
            'title' => 'Foreign Invoice',
            'status' => InvoiceStatusEnum::PAID,
            'client_id' => $client->id,
            'currency' => 'BRL',
            'total' => 1000.00,
            'company_id' => $this->company->id,
        ]);

        Livewire::withQueryParams(['clone' => $invoice->id])
            ->test(CreateInvoice::class)
            ->assertSet('data.currency', 'BRL');
    }
}
