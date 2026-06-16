<?php

namespace Tests\Feature;

use App\Enums\ExchangeRateSourceEnum;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ExchangeRateFetchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $codes
     */
    private function activateCurrencies(array $codes): void
    {
        foreach ($codes as $code) {
            Currency::firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'symbol' => $code, 'is_active' => true],
            );
        }
    }

    public function test_latest_fetch_creates_api_rows_under_payload_date(): void
    {
        $this->activateCurrencies(['USD', 'BRL', 'CAD', 'EUR', 'GBP']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0,
                'base' => 'USD',
                'date' => '2024-06-14',
                'rates' => ['BRL' => 5.12, 'CAD' => 1.37, 'EUR' => 0.93, 'GBP' => 0.78],
            ]),
        ]);

        $this->artisan('exchange-rate:fetch')->assertExitCode(0);

        $this->assertSame(4, ExchangeRate::count());
        $this->assertDatabaseHas('exchange_rates', [
            'currency_code' => 'BRL',
            'date' => '2024-06-14',
            'source' => ExchangeRateSourceEnum::Api->value,
            'is_frozen' => false,
        ]);
        $this->assertDatabaseMissing('exchange_rates', ['currency_code' => 'USD']);

        $brl = ExchangeRate::where('currency_code', 'BRL')->first();
        $this->assertEquals(5.12, (float) $brl->rate);
    }

    public function test_row_uses_payload_date_not_run_date(): void
    {
        $this->activateCurrencies(['USD', 'BRL']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0, 'base' => 'USD', 'date' => '2024-06-07',
                'rates' => ['BRL' => 5.05],
            ]),
        ]);

        $this->artisan('exchange-rate:fetch')->assertExitCode(0);

        $this->assertSame('2024-06-07', ExchangeRate::where('currency_code', 'BRL')->first()->date->toDateString());
        $this->assertSame(1, ExchangeRate::count());
    }

    public function test_rerun_same_date_is_idempotent_and_updates_rate(): void
    {
        $this->activateCurrencies(['USD', 'BRL']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::sequence()
                ->push(['amount' => 1.0, 'base' => 'USD', 'date' => '2024-06-14', 'rates' => ['BRL' => 5.00]])
                ->push(['amount' => 1.0, 'base' => 'USD', 'date' => '2024-06-14', 'rates' => ['BRL' => 5.50]]),
        ]);

        $this->artisan('exchange-rate:fetch')->assertExitCode(0);
        $this->artisan('exchange-rate:fetch')->assertExitCode(0);

        $this->assertSame(1, ExchangeRate::where('currency_code', 'BRL')->count());
        $this->assertEquals(5.50, (float) ExchangeRate::where('currency_code', 'BRL')->first()->rate);
    }

    public function test_does_not_overwrite_frozen_manual_row(): void
    {
        $this->activateCurrencies(['USD', 'BRL']);

        ExchangeRate::factory()->forCurrency('BRL')->on('2024-06-14')->frozen()->create(['rate' => 4.00]);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0, 'base' => 'USD', 'date' => '2024-06-14', 'rates' => ['BRL' => 5.50],
            ]),
        ]);

        $this->artisan('exchange-rate:fetch')->assertExitCode(0);

        $row = ExchangeRate::where('currency_code', 'BRL')->first();
        $this->assertEquals(4.00, (float) $row->rate);
        $this->assertTrue($row->is_frozen);
        $this->assertSame(ExchangeRateSourceEnum::Manual, $row->source);
        $this->assertSame(1, ExchangeRate::count());
    }

    public function test_network_failure_logs_and_returns_failure_without_writing(): void
    {
        Sleep::fake();
        Log::spy();
        $this->activateCurrencies(['USD', 'BRL']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response('', 500),
        ]);

        $this->artisan('exchange-rate:fetch')->assertExitCode(1);

        $this->assertSame(0, ExchangeRate::count());
        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    public function test_only_active_non_pivot_symbols_are_requested(): void
    {
        $this->activateCurrencies(['USD', 'BRL', 'EUR']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0, 'base' => 'USD', 'date' => '2024-06-14',
                'rates' => ['BRL' => 5.12, 'EUR' => 0.93],
            ]),
        ]);

        $this->artisan('exchange-rate:fetch')->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            $symbols = $request->data()['symbols'] ?? '';
            $list = explode(',', $symbols);

            return in_array('BRL', $list, true)
                && in_array('EUR', $list, true)
                && ! in_array('USD', $list, true)
                && ($request->data()['base'] ?? null) === 'USD';
        });
    }

    public function test_active_currency_missing_from_payload_logs_warning_and_succeeds(): void
    {
        Log::spy();
        $this->activateCurrencies(['USD', 'BRL', 'JPY']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0, 'base' => 'USD', 'date' => '2024-06-14',
                'rates' => ['BRL' => 5.12],
            ]),
        ]);

        $this->artisan('exchange-rate:fetch')->assertExitCode(0);

        $this->assertSame(1, ExchangeRate::count());
        $this->assertDatabaseHas('exchange_rates', ['currency_code' => 'BRL']);
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_only_usd_active_fetches_nothing(): void
    {
        $this->activateCurrencies(['USD']);

        Http::fake();

        $this->artisan('exchange-rate:fetch')->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame(0, ExchangeRate::count());
    }

    public function test_empty_rates_payload_succeeds_with_no_rows(): void
    {
        $this->activateCurrencies(['USD', 'BRL']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0, 'base' => 'USD', 'date' => '2024-06-14', 'rates' => [],
            ]),
        ]);

        $this->artisan('exchange-rate:fetch')->assertExitCode(0);

        $this->assertSame(0, ExchangeRate::count());
    }

    public function test_range_backfill_creates_one_row_per_date(): void
    {
        $this->activateCurrencies(['USD', 'BRL']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0, 'base' => 'USD',
                'start_date' => '2024-06-10', 'end_date' => '2024-06-11',
                'rates' => [
                    '2024-06-10' => ['BRL' => 5.10],
                    '2024-06-11' => ['BRL' => 5.20],
                ],
            ]),
        ]);

        $this->artisan('exchange-rate:fetch', ['--from' => '2024-06-10', '--to' => '2024-06-11'])
            ->assertExitCode(0);

        $this->assertSame(2, ExchangeRate::where('currency_code', 'BRL')->count());
        $this->assertEquals(5.10, (float) ExchangeRate::where('currency_code', 'BRL')->where('date', '2024-06-10')->first()->rate);
        $this->assertEquals(5.20, (float) ExchangeRate::where('currency_code', 'BRL')->where('date', '2024-06-11')->first()->rate);
    }

    public function test_currency_present_on_only_one_range_date_is_not_treated_as_missing(): void
    {
        Log::spy();
        $this->activateCurrencies(['USD', 'BRL', 'CAD']);

        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0, 'base' => 'USD',
                'start_date' => '2024-06-10', 'end_date' => '2024-06-11',
                'rates' => [
                    '2024-06-10' => ['BRL' => 5.10, 'CAD' => 1.37],
                    '2024-06-11' => ['BRL' => 5.20],
                ],
            ]),
        ]);

        $this->artisan('exchange-rate:fetch', ['--from' => '2024-06-10', '--to' => '2024-06-11'])
            ->assertExitCode(0);

        $this->assertSame(3, ExchangeRate::count());
        $this->assertSame(2, ExchangeRate::where('currency_code', 'BRL')->count());
        $this->assertSame(1, ExchangeRate::where('currency_code', 'CAD')->count());

        $this->assertDatabaseHas('exchange_rates', [
            'currency_code' => 'CAD',
            'date' => '2024-06-10',
            'source' => ExchangeRateSourceEnum::Api->value,
            'is_frozen' => false,
        ]);

        Log::shouldNotHaveReceived('warning', [
            'Currency requested but not returned by Frankfurter',
            ['currency' => 'CAD'],
        ]);
    }

    public function test_invalid_date_returns_failure_without_request(): void
    {
        $this->activateCurrencies(['USD', 'BRL']);
        Http::fake();

        $this->artisan('exchange-rate:fetch', ['--from' => 'not-a-date'])->assertExitCode(1);

        Http::assertNothingSent();
        $this->assertSame(0, ExchangeRate::count());
    }

    public function test_future_date_returns_failure(): void
    {
        $this->activateCurrencies(['USD', 'BRL']);
        Http::fake();

        $future = CarbonImmutable::today()->addDay()->toDateString();

        $this->artisan('exchange-rate:fetch', ['--from' => $future])->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_from_after_to_returns_failure(): void
    {
        $this->activateCurrencies(['USD', 'BRL']);
        Http::fake();

        $this->artisan('exchange-rate:fetch', ['--from' => '2024-06-11', '--to' => '2024-06-10'])
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_command_is_scheduled_daily_at_six(): void
    {
        $events = app(Schedule::class)->events();

        $match = collect($events)->first(
            fn ($event): bool => str_contains((string) $event->command, 'exchange-rate:fetch'),
        );

        $this->assertNotNull($match, 'exchange-rate:fetch is not scheduled.');
        $this->assertSame('0 6 * * *', $match->expression);
    }
}
