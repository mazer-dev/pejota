<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Filament\App\Pages\CompanySettings;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingsCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_currency_options_include_active_currencies_and_exclude_inactive(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Currency::factory()->create(['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'is_active' => true]);
        Currency::factory()->create(['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'is_active' => false]);

        $options = Livewire::test(CompanySettings::class)->instance()->baseCurrencyOptions();

        $this->assertArrayHasKey('BRL', $options);
        $this->assertArrayNotHasKey('JPY', $options);
    }

    public function test_base_currency_options_include_legacy_saved_value_even_if_inactive(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'XAU');
        Currency::factory()->create(['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'is_active' => true]);

        $options = Livewire::test(CompanySettings::class)->instance()->baseCurrencyOptions();

        $this->assertArrayHasKey('XAU', $options, 'O valor salvo (mesmo fora da lista ativa) deve permanecer ofertável.');
    }

    public function test_saving_persists_an_active_base_currency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Currency::factory()->create(['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'is_active' => true]);

        Livewire::test(CompanySettings::class)
            ->set('data.'.CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'BRL',
            $user->company->refresh()->settings()->get(CompanySettingsEnum::FINANCE_CURRENCY->value),
        );
    }
}
