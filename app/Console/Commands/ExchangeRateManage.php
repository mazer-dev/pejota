<?php

namespace App\Console\Commands;

use App\Enums\ExchangeRateSourceEnum;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Console\Command;

class ExchangeRateManage extends Command
{
    protected $signature = 'exchange-rate:manage';

    protected $description = 'List, set, unfreeze or delete exchange rates (global, USD-pivot)';

    public function handle(): int
    {
        $action = $this->choice('What do you want to do?', ['list', 'set', 'unfreeze', 'delete'], 'list');

        return match ($action) {
            'set' => $this->setRate(),
            'unfreeze' => $this->unfreezeRate(),
            'delete' => $this->deleteRate(),
            default => $this->listRates(),
        };
    }

    private function listRates(): int
    {
        $rows = ExchangeRate::query()
            ->orderBy('currency_code')
            ->orderByDesc('date')
            ->get()
            ->map(fn (ExchangeRate $rate): array => [
                $rate->currency_code,
                $rate->date->toDateString(),
                (string) $rate->rate,
                $rate->source->value,
                $rate->is_frozen ? 'yes' : 'no',
            ])
            ->all();

        $this->table(['Currency', 'Date', 'Rate (1 USD =)', 'Source', 'Frozen'], $rows);

        return self::SUCCESS;
    }

    private function setRate(): int
    {
        $code = strtoupper((string) $this->ask('Currency code (ISO 4217, 3 letters)'));

        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            $this->error('Invalid currency code. Use exactly 3 letters.');

            return self::FAILURE;
        }

        if ($code === ExchangeRateService::PIVOT) {
            $this->error('USD is the pivot (rate is always 1.0) and is not stored.');

            return self::FAILURE;
        }

        if (! Currency::query()->where('code', $code)->exists()) {
            $this->error("Currency {$code} does not exist.");

            return self::FAILURE;
        }

        try {
            $date = CarbonImmutable::parse((string) $this->ask('Date (YYYY-MM-DD)'))->startOfDay();
        } catch (Exception) {
            $this->error('Invalid date.');

            return self::FAILURE;
        }

        if ($date->isAfter(CarbonImmutable::now()->startOfDay())) {
            $this->error('Date cannot be in the future.');

            return self::FAILURE;
        }

        $rateInput = str_replace(',', '.', (string) $this->ask('Rate (1 USD = ? units of the currency)'));

        if (! is_numeric($rateInput) || (float) $rateInput <= 0) {
            $this->error('Rate must be a number greater than zero.');

            return self::FAILURE;
        }

        $exists = ExchangeRate::query()
            ->where('currency_code', $code)
            ->whereDate('date', $date->toDateString())
            ->exists();

        if ($exists && ! $this->confirm("A rate for {$code} on {$date->toDateString()} exists. Overwrite?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        ExchangeRate::updateOrCreate(
            ['currency_code' => $code, 'date' => $date->toDateString()],
            ['rate' => (float) $rateInput, 'source' => ExchangeRateSourceEnum::Manual, 'is_frozen' => true],
        );

        $this->info("Exchange rate for {$code} on {$date->toDateString()} saved.");

        return self::SUCCESS;
    }

    private function unfreezeRate(): int
    {
        $rate = $this->selectRate();

        if ($rate === null) {
            return self::FAILURE;
        }

        $rate->is_frozen = false;
        $rate->save();

        $this->info("Exchange rate for {$rate->currency_code} on {$rate->date->toDateString()} unfrozen.");

        return self::SUCCESS;
    }

    private function deleteRate(): int
    {
        $rate = $this->selectRate();

        if ($rate === null) {
            return self::FAILURE;
        }

        if (! $this->confirm("Delete the rate for {$rate->currency_code} on {$rate->date->toDateString()}?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $rate->delete();

        $this->info('Exchange rate deleted.');

        return self::SUCCESS;
    }

    private function selectRate(): ?ExchangeRate
    {
        $code = strtoupper((string) $this->ask('Currency code'));

        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            $this->error('Invalid currency code. Use exactly 3 letters.');

            return null;
        }

        try {
            $date = CarbonImmutable::parse((string) $this->ask('Date (YYYY-MM-DD)'))->startOfDay();
        } catch (Exception) {
            $this->error('Invalid date.');

            return null;
        }

        $rate = ExchangeRate::query()
            ->where('currency_code', $code)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($rate === null) {
            $this->error("No rate found for {$code} on {$date->toDateString()}.");

            return null;
        }

        return $rate;
    }
}
