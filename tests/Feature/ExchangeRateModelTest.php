<?php

namespace Tests\Feature;

use App\Enums\ExchangeRateSourceEnum;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_is_visible_across_tenants(): void
    {
        $rate = ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create();

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA);
        $this->assertTrue(ExchangeRate::query()->whereKey($rate->id)->exists());

        $this->actingAs($userB);
        $this->assertTrue(ExchangeRate::query()->whereKey($rate->id)->exists());
    }

    public function test_currency_date_pair_is_unique(): void
    {
        ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/UNIQUE/');

        ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create();
    }

    public function test_source_is_cast_to_enum(): void
    {
        $manual = ExchangeRate::factory()->forCurrency('BRL')->on('2026-01-10')->create();
        $api = ExchangeRate::factory()->forCurrency('EUR')->on('2026-01-10')->api()->create();

        $this->assertSame(ExchangeRateSourceEnum::Manual, $manual->fresh()->source);
        $this->assertSame(ExchangeRateSourceEnum::Api, $api->fresh()->source);
    }
}
