<?php

namespace Tests\Feature\UserSettings;

use App\Enums\CompanySettingsEnum;
use App\Enums\UserSettingsEnum;
use App\Filament\App\Pages\CompanySettings;
use App\Filament\App\Pages\MyPreferences;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class MyPreferencesPageTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    public function test_my_preferences_saves_localization_to_the_user(): void
    {
        $user = User::factory()->create();
        $this->actingInCompany($user);

        Livewire::test(MyPreferences::class)
            ->set('data.'.UserSettingsEnum::LOCALIZATION_LOCALE->value, 'pt_BR')
            ->set('data.'.UserSettingsEnum::LOCALIZATION_TIMEZONE->value, 'America/Sao_Paulo')
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $user->fresh();
        $this->assertSame('pt_BR', $fresh->settings()->get(UserSettingsEnum::LOCALIZATION_LOCALE->value));
        $this->assertSame('America/Sao_Paulo', $fresh->settings()->get(UserSettingsEnum::LOCALIZATION_TIMEZONE->value));
    }

    public function test_my_preferences_exposes_personal_fields(): void
    {
        $user = User::factory()->create();
        $this->actingInCompany($user);

        Livewire::test(MyPreferences::class)
            ->assertFormFieldExists(UserSettingsEnum::LOCALIZATION_LOCALE->value)
            ->assertFormFieldExists(UserSettingsEnum::TASKS_DEFAULT_LIST_COLUMNS->value);
    }

    public function test_company_settings_no_longer_exposes_personal_fields(): void
    {
        $user = User::factory()->create();
        $this->actingInCompany($user);

        Livewire::test(CompanySettings::class)
            ->assertFormFieldExists(CompanySettingsEnum::FINANCE_CURRENCY->value)
            ->assertFormFieldDoesNotExist(UserSettingsEnum::LOCALIZATION_LOCALE->value)
            ->assertFormFieldDoesNotExist(UserSettingsEnum::TASKS_DEFAULT_LIST_COLUMNS->value);
    }
}
