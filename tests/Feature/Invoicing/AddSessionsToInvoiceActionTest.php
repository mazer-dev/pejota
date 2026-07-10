<?php

namespace Tests\Feature\Invoicing;

use App\Filament\App\Resources\InvoiceResource\Pages\EditInvoice;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class AddSessionsToInvoiceActionTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    private Client $client;

    private Product $product;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
        $this->client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id, 'currency' => 'BRL']);
        $this->unit = Unit::create(['name' => 'Hour', 'symbol' => 'h', 'company_id' => $this->company->id]);
        $this->product = Product::create(['name' => 'Consulting', 'symbol' => 'C', 'service' => true, 'digital' => false, 'company_id' => $this->company->id, 'unit_id' => $this->unit->id, 'price' => 100, 'cost' => 0]);
    }

    private function invoice(string $currency = 'BRL'): Invoice
    {
        return Invoice::create(['number' => 'INV-1', 'title' => 'T', 'client_id' => $this->client->id, 'currency' => $currency, 'total' => 0]);
    }

    private function makeSession(): WorkSession
    {
        return WorkSession::create([
            'title' => 'Work', 'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'is_running' => false, 'rate' => 100.00, 'billable' => true,
            'start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 11:00:00', // 200.00
        ]);
    }

    private function data(): array
    {
        return [
            'from' => '2026-06-01',
            'to' => '2026-06-30',
            'grouping' => 'none',
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
        ];
    }

    public function test_action_appends_items_and_freezes_sessions(): void
    {
        $invoice = $this->invoice();
        $session = $this->makeSession();

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->callAction('addSessions', data: $this->data())
            ->assertHasNoActionErrors();

        $this->assertCount(1, $invoice->fresh()->items);
        $this->assertEqualsWithDelta(200.00, (float) $invoice->fresh()->total, 0.001);
        $this->assertNotNull($session->fresh()->invoice_item_id);
    }

    public function test_action_blocks_on_currency_mismatch(): void
    {
        $invoice = $this->invoice('USD');
        $this->makeSession();

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->callAction('addSessions', data: $this->data());

        $this->assertCount(0, $invoice->fresh()->items);
    }

    public function test_total_survives_a_manual_save_after_appending(): void
    {
        $invoice = $this->invoice();
        $this->makeSession();

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->callAction('addSessions', data: $this->data());

        // Re-open the freshly-updated invoice and save the form: calcInvoiceTotal must agree.
        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(200.00, (float) $invoice->fresh()->total, 0.001);
    }
}
