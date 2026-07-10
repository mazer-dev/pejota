<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Filament\App\Widgets\InvoicesOverview;
use App\Models\Client;
use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NumberFormatter;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class InvoicesOverviewConsolidationTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
        $this->company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');
        $this->client = Client::create(['name' => 'C', 'company_id' => $this->company->id]);
    }

    private function invoice(array $attributes): Invoice
    {
        return Invoice::create(array_merge([
            'number' => 'INV-'.fake()->unique()->numerify('####'),
            'title' => 'x', 'client_id' => $this->client->id,
            'company_id' => $this->company->id, 'total' => 100.00,
            'status' => InvoiceStatusEnum::SENT, 'due_date' => now()->addDays(5)->toDateString(),
        ], $attributes));
    }

    public function test_pending_total_consolidates_mixed_currencies_without_factor_100(): void
    {
        ExchangeRate::factory()->forCurrency('BRL')->on(now()->toDateString())->create(['rate' => 5.0]);
        $this->invoice(['currency' => 'BRL', 'total' => 100.00]);
        $this->invoice(['currency' => 'USD', 'total' => 100.00]);

        $fmt = NumberFormatter::create('en', NumberFormatter::CURRENCY);
        $expected = $fmt->formatCurrency(600.0, 'BRL');

        Livewire::test(InvoicesOverview::class)->assertSee($expected);
    }

    public function test_single_base_invoice_shows_its_value_not_one_hundredth(): void
    {
        $this->invoice(['currency' => 'BRL', 'total' => 100.00]);

        $fmt = NumberFormatter::create('en', NumberFormatter::CURRENCY);

        Livewire::test(InvoicesOverview::class)
            ->assertSee($fmt->formatCurrency(100.0, 'BRL'))
            ->assertDontSee($fmt->formatCurrency(1.0, 'BRL'));
    }
}
