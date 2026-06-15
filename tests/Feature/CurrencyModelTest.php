<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_scope_returns_only_active(): void
    {
        Currency::factory()->create(['code' => 'AAA', 'is_active' => true]);
        Currency::factory()->create(['code' => 'BBB', 'is_active' => false]);

        $codes = Currency::active()->pluck('code')->all();

        $this->assertContains('AAA', $codes);
        $this->assertNotContains('BBB', $codes);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $currency = Currency::factory()->create(['is_active' => 1]);

        $this->assertIsBool($currency->fresh()->is_active);
    }

    public function test_currency_is_visible_across_tenants(): void
    {
        $currency = Currency::factory()->create(['code' => 'ZZZ']);

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA);
        $this->assertTrue(Currency::query()->whereKey($currency->id)->exists());

        $this->actingAs($userB);
        $this->assertTrue(Currency::query()->whereKey($currency->id)->exists());
    }
}
