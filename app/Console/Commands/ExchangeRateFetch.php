<?php

namespace App\Console\Commands;

use App\Enums\ExchangeRateSourceEnum;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExchangeRateFetch extends Command
{
    protected $signature = 'exchange-rate:fetch {--from= : Start date YYYY-MM-DD for backfill} {--to= : End date YYYY-MM-DD (defaults to today)}';

    protected $description = 'Fetch ECB exchange rates from Frankfurter and upsert non-frozen rows (global, USD-pivot)';

    private const BASE_URL = 'https://api.frankfurter.dev/v2/';

    public function handle(): int
    {
        $symbols = Currency::query()
            ->active()
            ->pluck('code')
            ->reject(fn (string $code): bool => $code === ExchangeRateService::PIVOT)
            ->values()
            ->all();

        if ($symbols === []) {
            $this->info('No active currencies besides the USD pivot. Nothing to fetch.');

            return self::SUCCESS;
        }

        $endpoint = $this->resolveEndpoint();

        if ($endpoint === null) {
            return self::FAILURE;
        }

        try {
            $response = Http::timeout(15)
                ->retry(3, 500)
                ->get(self::BASE_URL.$endpoint, ['base' => ExchangeRateService::PIVOT, 'symbols' => implode(',', $symbols)]);
        } catch (RequestException|ConnectionException $e) {
            return $this->failRequest($endpoint, $e->getMessage(), $e);
        }

        return $this->processPayload($response->json(), $symbols);
    }

    private function resolveEndpoint(): ?string
    {
        $from = $this->option('from');

        if ($from === null) {
            return 'latest';
        }

        try {
            $fromDate = CarbonImmutable::parse((string) $from)->startOfDay();
            $toDate = $this->option('to') !== null
                ? CarbonImmutable::parse((string) $this->option('to'))->startOfDay()
                : CarbonImmutable::today();
        } catch (Exception) {
            $this->error('Invalid date. Use YYYY-MM-DD.');

            return null;
        }

        $today = CarbonImmutable::today();

        if ($fromDate->isAfter($today) || $toDate->isAfter($today)) {
            $this->error('Dates cannot be in the future.');

            return null;
        }

        if ($fromDate->isAfter($toDate)) {
            $this->error('--from must be on or before --to.');

            return null;
        }

        return $fromDate->toDateString().'..'.$toDate->toDateString();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $symbols
     */
    private function processPayload(array $payload, array $symbols): int
    {
        $rates = $payload['rates'] ?? [];

        $byDate = $this->normalize($rates, $payload['date'] ?? null);

        $seen = [];
        $upserted = 0;
        $skipped = 0;

        foreach ($byDate as $date => $currencies) {
            foreach ($currencies as $code => $rate) {
                $seen[$code] = true;

                if ($this->isFrozen($code, $date)) {
                    $skipped++;
                    Log::warning('Skipped frozen exchange rate', ['currency' => $code, 'date' => $date]);

                    continue;
                }

                ExchangeRate::updateOrCreate(
                    ['currency_code' => $code, 'date' => $date],
                    ['rate' => (float) $rate, 'source' => ExchangeRateSourceEnum::Api, 'is_frozen' => false],
                );
                $upserted++;
            }
        }

        $missing = array_values(array_diff($symbols, array_keys($seen)));

        foreach ($missing as $code) {
            Log::warning('Currency requested but not returned by Frankfurter', ['currency' => $code]);
        }

        $this->info("Done. Upserted: {$upserted}, skipped (frozen): {$skipped}, missing: ".count($missing).'.');

        return self::SUCCESS;
    }

    /**
     * Normalize both payload shapes into [ 'Y-m-d' => [ code => rate ] ].
     * latest: rates is a flat map and $latestDate carries the date.
     * range: rates is keyed by date.
     *
     * @param  array<string, mixed>  $rates
     * @return array<string, array<string, float|int>>
     */
    private function normalize(array $rates, ?string $latestDate): array
    {
        if ($rates === []) {
            return [];
        }

        $first = reset($rates);

        if (is_array($first)) {
            return $rates;
        }

        if ($latestDate === null) {
            Log::warning('Frankfurter payload missing date for flat rates');

            return [];
        }

        return [$latestDate => $rates];
    }

    private function isFrozen(string $code, string $date): bool
    {
        return ExchangeRate::query()
            ->where('currency_code', $code)
            ->where('date', $date)
            ->where('is_frozen', true)
            ->exists();
    }

    private function failRequest(string $endpoint, string $reason, ?Throwable $exception = null): int
    {
        Log::error('Frankfurter fetch failed', ['endpoint' => $endpoint, 'reason' => $reason]);
        report($exception ?? new Exception("Frankfurter fetch failed ({$endpoint}): {$reason}"));
        $this->error("Failed to fetch exchange rates: {$reason}");

        return self::FAILURE;
    }
}
