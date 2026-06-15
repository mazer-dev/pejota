<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateManageCommandTest extends TestCase
{
    use RefreshDatabase;

    private const OPTIONS = ['list', 'set', 'unfreeze', 'delete'];

    public function test_set_creates_a_manual_frozen_rate(): void
    {
        Currency::factory()->create(['code' => 'BRL']);

        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'set', self::OPTIONS)
            ->expectsQuestion('Currency code (ISO 4217, 3 letters)', 'brl')
            ->expectsQuestion('Date (YYYY-MM-DD)', '2026-01-10')
            ->expectsQuestion('Rate (1 USD = ? units of the currency)', '5.20')
            ->expectsOutputToContain('saved')
            ->assertExitCode(0);

        $this->assertDatabaseHas('exchange_rates', [
            'currency_code' => 'BRL', 'date' => '2026-01-10', 'source' => 'manual', 'is_frozen' => 1,
        ]);
        $this->assertEqualsWithDelta(5.2, (float) ExchangeRate::first()->rate, 0.0000001);
    }

    public function test_set_normalizes_comma_decimal(): void
    {
        Currency::factory()->create(['code' => 'BRL']);

        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'set', self::OPTIONS)
            ->expectsQuestion('Currency code (ISO 4217, 3 letters)', 'BRL')
            ->expectsQuestion('Date (YYYY-MM-DD)', '2026-01-10')
            ->expectsQuestion('Rate (1 USD = ? units of the currency)', '5,20')
            ->assertExitCode(0);

        $this->assertEqualsWithDelta(5.2, (float) ExchangeRate::first()->rate, 0.0000001);
    }

    public function test_set_overwrites_existing_after_confirmation(): void
    {
        ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create(['rate' => 5.0]);

        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'set', self::OPTIONS)
            ->expectsQuestion('Currency code (ISO 4217, 3 letters)', 'BRL')
            ->expectsQuestion('Date (YYYY-MM-DD)', '2026-01-10')
            ->expectsQuestion('Rate (1 USD = ? units of the currency)', '6.00')
            ->expectsConfirmation('A rate for BRL on 2026-01-10 exists. Overwrite?', 'yes')
            ->expectsOutputToContain('saved')
            ->assertExitCode(0);

        $this->assertSame(1, ExchangeRate::count());
        $this->assertEqualsWithDelta(6.0, (float) ExchangeRate::first()->rate, 0.0000001);
    }

    public function test_set_aborts_when_overwrite_declined(): void
    {
        ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create(['rate' => 5.0]);

        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'set', self::OPTIONS)
            ->expectsQuestion('Currency code (ISO 4217, 3 letters)', 'BRL')
            ->expectsQuestion('Date (YYYY-MM-DD)', '2026-01-10')
            ->expectsQuestion('Rate (1 USD = ? units of the currency)', '6.00')
            ->expectsConfirmation('A rate for BRL on 2026-01-10 exists. Overwrite?', 'no')
            ->expectsOutputToContain('Aborted')
            ->assertExitCode(0);

        $this->assertSame(1, ExchangeRate::count());
        $this->assertEqualsWithDelta(5.0, (float) ExchangeRate::first()->rate, 0.0000001);
    }

    public function test_set_rejects_usd_pivot(): void
    {
        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'set', self::OPTIONS)
            ->expectsQuestion('Currency code (ISO 4217, 3 letters)', 'USD')
            ->expectsOutputToContain('pivot')
            ->assertExitCode(1);
    }

    public function test_set_rejects_unknown_currency(): void
    {
        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'set', self::OPTIONS)
            ->expectsQuestion('Currency code (ISO 4217, 3 letters)', 'GBP')
            ->expectsOutputToContain('does not exist')
            ->assertExitCode(1);
    }

    public function test_set_rejects_future_date(): void
    {
        Currency::factory()->create(['code' => 'BRL']);

        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'set', self::OPTIONS)
            ->expectsQuestion('Currency code (ISO 4217, 3 letters)', 'BRL')
            ->expectsQuestion('Date (YYYY-MM-DD)', '2099-01-01')
            ->expectsOutputToContain('future')
            ->assertExitCode(1);
    }

    public function test_set_rejects_non_positive_rate(): void
    {
        Currency::factory()->create(['code' => 'BRL']);

        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'set', self::OPTIONS)
            ->expectsQuestion('Currency code (ISO 4217, 3 letters)', 'BRL')
            ->expectsQuestion('Date (YYYY-MM-DD)', '2026-01-10')
            ->expectsQuestion('Rate (1 USD = ? units of the currency)', '0')
            ->expectsOutputToContain('greater than zero')
            ->assertExitCode(1);
    }

    public function test_unfreeze_clears_frozen_flag(): void
    {
        $rate = ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create(['is_frozen' => true]);

        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'unfreeze', self::OPTIONS)
            ->expectsQuestion('Currency code', 'BRL')
            ->expectsQuestion('Date (YYYY-MM-DD)', '2026-01-10')
            ->expectsOutputToContain('unfrozen')
            ->assertExitCode(0);

        $this->assertFalse($rate->fresh()->is_frozen);
    }

    public function test_delete_removes_the_rate(): void
    {
        $rate = ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create();

        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'delete', self::OPTIONS)
            ->expectsQuestion('Currency code', 'BRL')
            ->expectsQuestion('Date (YYYY-MM-DD)', '2026-01-10')
            ->expectsConfirmation('Delete the rate for BRL on 2026-01-10?', 'yes')
            ->expectsOutputToContain('deleted')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('exchange_rates', ['id' => $rate->id]);
    }

    public function test_delete_aborts_when_declined(): void
    {
        $rate = ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create();

        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'delete', self::OPTIONS)
            ->expectsQuestion('Currency code', 'BRL')
            ->expectsQuestion('Date (YYYY-MM-DD)', '2026-01-10')
            ->expectsConfirmation('Delete the rate for BRL on 2026-01-10?', 'no')
            ->expectsOutputToContain('Aborted')
            ->assertExitCode(0);

        $this->assertDatabaseHas('exchange_rates', ['id' => $rate->id]);
    }

    public function test_select_rejects_invalid_date(): void
    {
        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'unfreeze', self::OPTIONS)
            ->expectsQuestion('Currency code', 'BRL')
            ->expectsQuestion('Date (YYYY-MM-DD)', 'foobar')
            ->expectsOutputToContain('Invalid date')
            ->assertExitCode(1);
    }

    public function test_list_outputs_existing_rates(): void
    {
        ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create();

        $this->artisan('exchange-rate:manage')
            ->expectsChoice('What do you want to do?', 'list', self::OPTIONS)
            ->expectsOutputToContain('BRL')
            ->assertExitCode(0);
    }
}
