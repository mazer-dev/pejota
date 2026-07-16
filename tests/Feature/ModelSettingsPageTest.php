<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Enums\UserSettingsEnum;
use App\Filament\App\Pages\CompanySettings;
use App\Filament\App\Pages\MyPreferences;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class ModelSettingsPageTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    public function test_my_preferences_persists_setting_to_user_settings(): void
    {
        $user = User::factory()->create();
        $this->actingInCompany($user);

        Livewire::test(MyPreferences::class)
            ->set('data.'.UserSettingsEnum::LOCALIZATION_LOCALE->value, 'pt_BR')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'pt_BR',
            $user->refresh()->settings()->get(UserSettingsEnum::LOCALIZATION_LOCALE->value),
        );
    }

    public function test_company_settings_persists_setting_to_company_settings(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);

        Currency::factory()->create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);

        Livewire::test(CompanySettings::class)
            ->set('data.'.CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value, 'YM000')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'YM000',
            $company->refresh()->settings()->get(CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value),
        );
    }

    public function test_saving_company_settings_preserves_settings_without_a_form_field(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);

        Currency::factory()->create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);

        $company->settings()->set(CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->value, 42);
        $company->settings()->set(CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST_PERIOD->value, '2604');

        Livewire::test(CompanySettings::class)
            ->set('data.'.CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value, 'YM000')
            ->call('save')
            ->assertHasNoFormErrors();

        $company->refresh();

        $this->assertSame(
            'YM000',
            $company->settings()->get(CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value),
        );
        $this->assertSame(
            42,
            $company->settings()->get(CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->value),
            'A settings key without a form field (docs.invoice_number_last) must survive a save.',
        );
        $this->assertSame(
            '2604',
            $company->settings()->get(CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST_PERIOD->value),
            'A sibling settings key without a form field must survive a save.',
        );
    }
}
