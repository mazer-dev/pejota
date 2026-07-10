<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Filament\App\Resources\ExchangeRateResource;
use App\Filament\App\Resources\ExchangeRateResource\Pages\ListExchangeRates;
use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class ExchangeRateResourceTest extends TestCase
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

    public function test_lists_exchange_rates(): void
    {
        $rate = ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create();

        Livewire::test(ListExchangeRates::class)
            ->assertCanSeeTableRecords([$rate]);
    }

    public function test_filters_by_currency(): void
    {
        $brl = ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create();
        $eur = ExchangeRate::factory()->forCurrency('EUR')->on('2026-01-10')->create();

        Livewire::test(ListExchangeRates::class)
            ->filterTable('currency_code', 'BRL')
            ->assertCanSeeTableRecords([$brl])
            ->assertCanNotSeeTableRecords([$eur]);
    }

    public function test_gates_are_read_only(): void
    {
        $rate = ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create();

        $this->assertFalse(ExchangeRateResource::canCreate());
        $this->assertFalse(ExchangeRateResource::canEdit($rate));
        $this->assertFalse(ExchangeRateResource::canDelete($rate));
        $this->assertFalse(ExchangeRateResource::canDeleteAny());
        $this->assertEqualsCanonicalizing(
            ['index', 'view'],
            array_keys(ExchangeRateResource::getPages()),
        );
    }

    public function test_filters_by_date_range(): void
    {
        $inside = ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create();
        $outside = ExchangeRate::factory()->forCurrency('EUR')->on('2026-03-10')->create();

        Livewire::test(ListExchangeRates::class)
            ->filterTable('date', ['from' => '2026-01-01', 'until' => '2026-01-31'])
            ->assertCanSeeTableRecords([$inside])
            ->assertCanNotSeeTableRecords([$outside]);
    }

    public function test_derived_column_shows_dash_when_base_rate_missing(): void
    {
        // Base da empresa = EUR, mas não há taxa de EUR -> triangulação falha -> '—'.
        $this->company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'EUR');

        $rate = ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create(['rate' => 5.0]);

        Livewire::test(ListExchangeRates::class)
            ->assertTableColumnStateSet('base_value', '—', $rate);
    }

    public function test_derived_column_shows_numeric_value_when_base_rate_available(): void
    {
        // Base = EUR; existem taxas de BRL e EUR -> base_value = convert(1, BRL, EUR) = 0.9/5.0.
        $this->company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'EUR');

        ExchangeRate::factory()->forCurrency('EUR')->on('2026-01-10')->create(['rate' => 0.9]);
        $rate = ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create(['rate' => 5.0]);

        Livewire::test(ListExchangeRates::class)
            ->assertTableColumnStateSet('base_value', number_format(0.9 / 5.0, 6), $rate);
    }
}
