<?php

namespace Tests\Feature;

use App\Exceptions\MissingExchangeRateException;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExchangeRateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExchangeRateService;
    }

    public function test_pivot_rate_is_one_without_db(): void
    {
        $this->assertDatabaseCount('exchange_rates', 0);

        $this->assertSame(1.0, $this->service->rateOn('USD', CarbonImmutable::parse('2026-01-10')));
    }

    public function test_exact_date_lookup(): void
    {
        ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create(['rate' => 5.0]);

        $this->assertSame(5.0, $this->service->rateOn('BRL', CarbonImmutable::parse('2026-01-10')));
    }

    public function test_carry_forward_uses_last_prior_rate(): void
    {
        ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create(['rate' => 5.0]);

        $this->assertSame(5.0, $this->service->rateOn('BRL', CarbonImmutable::parse('2026-01-15')));
    }

    public function test_throws_when_no_prior_rate(): void
    {
        ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create(['rate' => 5.0]);

        $this->expectException(MissingExchangeRateException::class);

        $this->service->rateOn('BRL', CarbonImmutable::parse('2026-01-05'));
    }

    public function test_triangulation_between_two_non_pivot_currencies(): void
    {
        ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create(['rate' => 5.0]);
        ExchangeRate::factory()->forCurrency('EUR')->on('2026-01-10')->create(['rate' => 0.9]);

        // 100 BRL -> EUR = 100 * rateOn(EUR)/rateOn(BRL) = 100 * 0.9/5.0 = 18
        $this->assertEqualsWithDelta(
            18.0,
            $this->service->convert(100.0, 'BRL', 'EUR', CarbonImmutable::parse('2026-01-10')),
            0.0000001,
        );
    }

    public function test_same_currency_returns_amount_without_lookup(): void
    {
        // Sem linhas no banco: se tocasse o banco lançaria MissingExchangeRateException.
        $this->assertDatabaseCount('exchange_rates', 0);

        $this->assertSame(
            100.0,
            $this->service->convert(100.0, 'BRL', 'BRL', CarbonImmutable::parse('2026-01-10')),
        );
    }

    public function test_converts_from_pivot_to_currency(): void
    {
        ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create(['rate' => 5.0]);

        // 100 USD -> BRL = 100 * rateOn(BRL)/rateOn(USD) = 100 * 5.0/1.0 = 500
        $this->assertEqualsWithDelta(
            500.0,
            $this->service->convert(100.0, 'USD', 'BRL', CarbonImmutable::parse('2026-01-10')),
            0.0000001,
        );
    }

    public function test_converts_from_currency_to_pivot(): void
    {
        ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create(['rate' => 5.0]);

        // 500 BRL -> USD = 500 * rateOn(USD)/rateOn(BRL) = 500 * 1.0/5.0 = 100
        $this->assertEqualsWithDelta(
            100.0,
            $this->service->convert(500.0, 'BRL', 'USD', CarbonImmutable::parse('2026-01-10')),
            0.0000001,
        );
    }
}
