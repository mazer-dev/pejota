<?php

namespace Tests\Feature;

use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyManageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_valid_currency_and_uppercases_the_code(): void
    {
        $this->artisan('currency:manage')
            ->expectsChoice('What do you want to do?', 'create', ['list', 'create', 'edit'])
            ->expectsQuestion('Currency code (ISO 4217, 3 letters)', 'jpy')
            ->expectsQuestion('Currency name (canonical, English)', 'Japanese Yen')
            ->expectsQuestion('Currency symbol', '¥')
            ->expectsOutputToContain('created')
            ->assertExitCode(0);

        $this->assertDatabaseHas('currencies', [
            'code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'is_active' => 1,
        ]);
    }

    public function test_rejects_duplicate_code(): void
    {
        Currency::factory()->create(['code' => 'USD']);

        $this->artisan('currency:manage')
            ->expectsChoice('What do you want to do?', 'create', ['list', 'create', 'edit'])
            ->expectsQuestion('Currency code (ISO 4217, 3 letters)', 'USD')
            ->expectsOutputToContain('already exists')
            ->assertExitCode(1);
    }

    public function test_rejects_invalid_code(): void
    {
        $this->artisan('currency:manage')
            ->expectsChoice('What do you want to do?', 'create', ['list', 'create', 'edit'])
            ->expectsQuestion('Currency code (ISO 4217, 3 letters)', 'US1')
            ->expectsOutputToContain('Invalid code')
            ->assertExitCode(1);
    }

    public function test_edits_existing_currency(): void
    {
        Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_active' => true]);

        $this->artisan('currency:manage')
            ->expectsChoice('What do you want to do?', 'edit', ['list', 'create', 'edit'])
            ->expectsChoice('Which currency?', 'EUR', ['EUR'])
            ->expectsQuestion('Name', 'Euro Updated')
            ->expectsQuestion('Symbol', '€')
            ->expectsConfirmation('Active?', 'no')
            ->assertExitCode(0);

        $this->assertDatabaseHas('currencies', [
            'code' => 'EUR', 'name' => 'Euro Updated', 'is_active' => false,
        ]);
    }
}
