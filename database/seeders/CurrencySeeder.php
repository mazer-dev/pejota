<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$'],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => 'US$'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'Pound Sterling', 'symbol' => '£'],
        ];

        foreach ($currencies as $data) {
            Currency::updateOrCreate(
                ['code' => $data['code']],
                ['name' => $data['name'], 'symbol' => $data['symbol']],
            );
        }
    }
}
