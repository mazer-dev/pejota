<?php

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;

class CurrencyManage extends Command
{
    protected $signature = 'currency:manage';

    protected $description = 'List, create or edit reference currencies (global)';

    public function handle(): int
    {
        $action = $this->choice('What do you want to do?', ['list', 'create', 'edit'], 'list');

        return match ($action) {
            'create' => $this->createCurrency(),
            'edit' => $this->editCurrency(),
            default => $this->listCurrencies(),
        };
    }

    private function listCurrencies(): int
    {
        $rows = Currency::query()->orderBy('code')->get()
            ->map(fn (Currency $currency): array => [
                $currency->code,
                $currency->name,
                $currency->symbol,
                $currency->is_active ? 'active' : 'inactive',
            ])
            ->all();

        $this->table(['Code', 'Name', 'Symbol', 'Status'], $rows);

        return self::SUCCESS;
    }

    private function createCurrency(): int
    {
        $code = strtoupper((string) $this->ask('Currency code (ISO 4217, 3 letters)'));

        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            $this->error('Invalid code. Use exactly 3 letters.');

            return self::FAILURE;
        }

        if (Currency::query()->where('code', $code)->exists()) {
            $this->error("Currency {$code} already exists.");

            return self::FAILURE;
        }

        $name = (string) $this->ask('Currency name (canonical, English)');
        $symbol = (string) $this->ask('Currency symbol');

        Currency::create([
            'code' => $code,
            'name' => $name,
            'symbol' => $symbol,
            'is_active' => true,
        ]);

        $this->info("Currency {$code} created.");

        return self::SUCCESS;
    }

    private function editCurrency(): int
    {
        $codes = Currency::query()->orderBy('code')->pluck('code')->all();

        if ($codes === []) {
            $this->error('No currencies to edit.');

            return self::FAILURE;
        }

        $code = $this->choice('Which currency?', $codes);
        $currency = Currency::query()->where('code', $code)->first();

        $currency->name = (string) $this->ask('Name', $currency->name);
        $currency->symbol = (string) $this->ask('Symbol', $currency->symbol);
        $currency->is_active = $this->confirm('Active?', $currency->is_active);
        $currency->save();

        $this->info("Currency {$code} updated.");

        return self::SUCCESS;
    }
}
