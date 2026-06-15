<?php

namespace Tests\Feature;

use App\Models\Currency;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_five_reference_currencies(): void
    {
        $this->seed(CurrencySeeder::class);

        $this->assertDatabaseCount('currencies', 5);
        $this->assertDatabaseHas('currencies', ['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'is_active' => 1]);
        $this->assertDatabaseHas('currencies', ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => 'US$']);
        $this->assertDatabaseHas('currencies', ['code' => 'CAD']);
        $this->assertDatabaseHas('currencies', ['code' => 'EUR']);
        $this->assertDatabaseHas('currencies', ['code' => 'GBP']);
    }

    public function test_is_idempotent(): void
    {
        $this->seed(CurrencySeeder::class);
        $this->seed(CurrencySeeder::class);

        $this->assertDatabaseCount('currencies', 5);
    }

    public function test_does_not_reactivate_manually_deactivated_currency(): void
    {
        $this->seed(CurrencySeeder::class);
        Currency::where('code', 'USD')->update(['is_active' => false]);

        $this->seed(CurrencySeeder::class);

        $this->assertFalse((bool) Currency::where('code', 'USD')->value('is_active'));
    }
}
