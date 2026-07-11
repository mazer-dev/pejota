<?php

namespace Tests\Feature\Localization;

use App\Enums\CompanySettingsEnum;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleFromCompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_is_applied_from_company_settings_on_a_tenant_page(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $company->settings()->set(CompanySettingsEnum::LOCALIZATION_LOCALE->value, 'pt_BR');

        $this->actingAs($user);

        $this->get(Filament::getPanel('app')->getUrl($company));

        $this->assertSame('pt_BR', app()->getLocale());
    }
}
