<?php

namespace Tests\Feature\UserSettings;

use App\Enums\CompanySettingsEnum;
use App\Enums\UserSettingsEnum;
use App\Helpers\PejotaHelper;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class PersonalSettingsSeamTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    public function test_personal_getters_come_from_user_and_survive_company_switch(): void
    {
        $user = User::factory()->create();
        $companyA = $user->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $companyB = (new CompanyService)->create($user);

        // Localização por-empresa difere entre A e B — DEVE ser ignorada pelos getters pessoais.
        $companyA->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'pt_BR');
        $companyB->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'es');
        // Currency por-empresa — DEVE seguir a empresa ativa.
        $companyA->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');
        $companyB->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'USD');

        // Preferência pessoal do user (diferente das duas empresas).
        $user->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'en');
        $user->settings()->set(UserSettingsEnum::LOCALIZATION_TIMEZONE->value, 'Asia/Tokyo');

        // Empresa ativa = A.
        $this->actingInCompany($user, $companyA);
        $this->assertSame('en', PejotaHelper::getUserLocate());            // user, não o pt_BR de A
        $this->assertSame('Asia/Tokyo', PejotaHelper::getUserTimeZone());  // user
        $this->assertSame('BRL', PejotaHelper::getUserCurrency());         // empresa A

        // Troca a empresa ativa = B.
        $this->actingInCompany($user, $companyB);
        $this->assertSame('en', PejotaHelper::getUserLocate());            // AINDA o user (não es)
        $this->assertSame('Asia/Tokyo', PejotaHelper::getUserTimeZone());  // AINDA o user
        $this->assertSame('USD', PejotaHelper::getUserCurrency());         // empresa B (mudou!)
    }
}
