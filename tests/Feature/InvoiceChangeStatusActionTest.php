<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Filament\App\Resources\InvoiceResource\Pages\ListInvoices;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceChangeStatusActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->client = Client::create([
            'name' => 'Acme',
            'company_id' => $this->user->company->id,
        ]);
    }

    private function makeInvoice(InvoiceStatusEnum $status, ?string $paymentDate = null): Invoice
    {
        return Invoice::create([
            'number' => 'INV-'.fake()->unique()->numerify('####'),
            'title' => 'Invoice',
            'status' => $status,
            'client_id' => $this->client->id,
            'due_date' => now()->toDateString(),
            'payment_date' => $paymentDate,
            'total' => 100.00,
            'company_id' => $this->user->company->id,
        ]);
    }

    public function test_changing_status_to_paid_sets_payment_date_to_today_when_empty(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatusEnum::SENT);

        Livewire::test(ListInvoices::class)
            ->set('activeTab', 'all')
            ->callTableAction('change_status', $invoice, data: [
                'status' => InvoiceStatusEnum::PAID->value,
            ])
            ->assertHasNoTableActionErrors();

        $invoice->refresh();

        $this->assertSame(InvoiceStatusEnum::PAID, $invoice->status);
        $this->assertSame(now()->toDateString(), $invoice->payment_date->toDateString());
    }

    public function test_changing_status_to_paid_keeps_existing_payment_date(): void
    {
        $existing = now()->subDays(10)->toDateString();
        $invoice = $this->makeInvoice(InvoiceStatusEnum::SENT, $existing);

        Livewire::test(ListInvoices::class)
            ->set('activeTab', 'all')
            ->callTableAction('change_status', $invoice, data: [
                'status' => InvoiceStatusEnum::PAID->value,
            ])
            ->assertHasNoTableActionErrors();

        $invoice->refresh();

        $this->assertSame(InvoiceStatusEnum::PAID, $invoice->status);
        $this->assertSame($existing, $invoice->payment_date->toDateString());
    }

    public function test_changing_status_to_unpaid_clears_payment_date(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatusEnum::PAID, now()->toDateString());

        Livewire::test(ListInvoices::class)
            ->set('activeTab', 'all')
            ->callTableAction('change_status', $invoice, data: [
                'status' => InvoiceStatusEnum::UNPAID->value,
            ])
            ->assertHasNoTableActionErrors();

        $invoice->refresh();

        $this->assertSame(InvoiceStatusEnum::UNPAID, $invoice->status);
        $this->assertNull($invoice->payment_date);
    }

    public function test_changing_status_to_canceled_clears_payment_date(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatusEnum::PAID, now()->toDateString());

        Livewire::test(ListInvoices::class)
            ->set('activeTab', 'all')
            ->callTableAction('change_status', $invoice, data: [
                'status' => InvoiceStatusEnum::CANCELED->value,
            ])
            ->assertHasNoTableActionErrors();

        $invoice->refresh();

        $this->assertSame(InvoiceStatusEnum::CANCELED, $invoice->status);
        $this->assertNull($invoice->payment_date);
    }
}
