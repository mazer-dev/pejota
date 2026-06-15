<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceServiceConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->user->company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');
        $this->actingAs($this->user);
        $this->client = Client::create(['name' => 'C', 'company_id' => $this->user->company->id]);
    }

    private function invoice(array $attributes): Invoice
    {
        return Invoice::create(array_merge([
            'number' => 'INV-'.fake()->unique()->numerify('####'),
            'title' => 'x', 'client_id' => $this->client->id,
            'company_id' => $this->user->company->id, 'total' => 100.00,
            'status' => InvoiceStatusEnum::SENT,
        ], $attributes));
    }

    public function test_sums_mixed_currencies_in_base_without_factor_100_error(): void
    {
        ExchangeRate::factory()->forCurrency('BRL')->on(now()->toDateString())->create(['rate' => 5.0]);
        $base = $this->invoice(['currency' => 'BRL', 'total' => 100.00]);
        $usd = $this->invoice(['currency' => 'USD', 'total' => 100.00]);

        $result = (new InvoiceService)->sumBaseCurrency([$base, $usd]);

        $this->assertEqualsWithDelta(600.00, $result['total'], 0.001);
        $this->assertSame(0, $result['unconverted']);
    }

    public function test_excludes_and_counts_invoices_without_quote(): void
    {
        $usd = $this->invoice(['currency' => 'USD', 'total' => 100.00]);

        $result = (new InvoiceService)->sumBaseCurrency([$usd]);

        $this->assertEqualsWithDelta(0.0, $result['total'], 0.001);
        $this->assertSame(1, $result['unconverted']);
    }
}
