<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Filament\App\Resources\InvoiceResource\Pages\ListInvoices;
use App\Models\Client;
use App\Models\ExchangeRate;
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

    private function makeInvoice(InvoiceStatusEnum $status, ?string $paymentDate = null, ?string $dueDate = null): Invoice
    {
        return Invoice::create([
            'number' => 'INV-'.fake()->unique()->numerify('####'),
            'title' => 'Invoice',
            'status' => $status,
            'client_id' => $this->client->id,
            'due_date' => $dueDate ?? now()->toDateString(),
            'payment_date' => $paymentDate,
            'total' => 100.00,
            'company_id' => $this->user->company->id,
        ]);
    }

    public function test_changing_status_to_paid_sets_payment_date_to_due_date_when_empty(): void
    {
        $dueDate = now()->addDays(7)->toDateString();
        $invoice = $this->makeInvoice(InvoiceStatusEnum::SENT, dueDate: $dueDate);

        Livewire::test(ListInvoices::class)
            ->set('activeTab', 'all')
            ->callTableAction('change_status', $invoice, data: [
                'status' => InvoiceStatusEnum::PAID->value,
            ])
            ->assertHasNoTableActionErrors();

        $invoice->refresh();

        $this->assertSame(InvoiceStatusEnum::PAID, $invoice->status);
        $this->assertSame($dueDate, $invoice->payment_date->toDateString());
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

    private function makeForeignInvoice(string $currency, ?string $paymentDate = null): Invoice
    {
        return Invoice::create([
            'number' => 'INV-'.fake()->unique()->numerify('####'),
            'title' => 'Invoice', 'status' => InvoiceStatusEnum::SENT,
            'client_id' => $this->client->id, 'currency' => $currency,
            'due_date' => now()->toDateString(), 'payment_date' => $paymentDate,
            'total' => 100.00, 'company_id' => $this->user->company->id,
        ]);
    }

    public function test_paying_base_currency_invoice_freezes_rate_one(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatusEnum::SENT);

        Livewire::test(ListInvoices::class)
            ->set('activeTab', 'all')
            ->callTableAction('change_status', $invoice, data: ['status' => InvoiceStatusEnum::PAID->value])
            ->assertHasNoTableActionErrors();

        $this->assertEqualsWithDelta(1.0, (float) $invoice->refresh()->exchange_rate, 0.0000001);
    }

    public function test_paying_foreign_invoice_with_quote_freezes_triangulated_rate(): void
    {
        $this->user->company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');
        ExchangeRate::factory()->forCurrency('BRL')->on(now()->toDateString())->create(['rate' => 4.8]);
        $invoice = $this->makeForeignInvoice('USD');

        Livewire::test(ListInvoices::class)
            ->set('activeTab', 'all')
            ->callTableAction('change_status', $invoice, data: [
                'status' => InvoiceStatusEnum::PAID->value,
                'payment_date' => now()->toDateString(),
                'realized_rate' => 5.0,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertEqualsWithDelta(5.0, (float) $invoice->refresh()->exchange_rate, 0.0000001);
    }

    public function test_paying_foreign_invoice_with_manual_rate_freezes_manual_value(): void
    {
        $this->user->company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');
        $invoice = $this->makeForeignInvoice('USD');

        Livewire::test(ListInvoices::class)
            ->set('activeTab', 'all')
            ->callTableAction('change_status', $invoice, data: [
                'status' => InvoiceStatusEnum::PAID->value,
                'payment_date' => now()->toDateString(),
                'realized_rate' => 5.42,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertEqualsWithDelta(5.42, (float) $invoice->refresh()->exchange_rate, 0.0000001);
    }

    public function test_moving_from_paid_to_partially_paid_clears_rate(): void
    {
        $this->user->company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');
        ExchangeRate::factory()->forCurrency('BRL')->on(now()->toDateString())->create(['rate' => 5.0]);
        $invoice = $this->makeForeignInvoice('USD', now()->toDateString());
        $invoice->update(['status' => InvoiceStatusEnum::PAID]);
        $this->assertNotNull($invoice->refresh()->exchange_rate);

        Livewire::test(ListInvoices::class)
            ->set('activeTab', 'all')
            ->callTableAction('change_status', $invoice, data: [
                'status' => InvoiceStatusEnum::PARTIALLY_PAID->value,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertNull($invoice->refresh()->exchange_rate);
    }
}
