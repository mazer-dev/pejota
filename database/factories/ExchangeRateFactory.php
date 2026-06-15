<?php

namespace Database\Factories;

use App\Enums\ExchangeRateSourceEnum;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        Currency::firstOrCreate(
            ['code' => 'BRL'],
            ['name' => 'Brazilian Real', 'symbol' => 'R$', 'is_active' => true],
        );

        return [
            'currency_code' => 'BRL',
            'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'rate' => fake()->randomFloat(6, 0.5, 6),
            'source' => ExchangeRateSourceEnum::Manual,
            'is_frozen' => true,
        ];
    }

    public function api(): static
    {
        return $this->state(fn (): array => [
            'source' => ExchangeRateSourceEnum::Api,
            'is_frozen' => false,
        ]);
    }

    public function frozen(): static
    {
        return $this->state(fn (): array => ['is_frozen' => true]);
    }

    public function forCurrency(string $code): static
    {
        Currency::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'symbol' => $code, 'is_active' => true],
        );

        return $this->state(fn (): array => ['currency_code' => $code]);
    }

    public function on(string $date): static
    {
        return $this->state(fn (): array => ['date' => $date]);
    }
}
