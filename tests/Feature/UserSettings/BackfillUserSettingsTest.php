<?php

namespace Tests\Feature\UserSettings;

use App\Enums\UserSettingsEnum;
use App\Models\User;
use App\Services\BackfillUserSettings;
use App\Services\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillUserSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_copies_localization_from_the_owned_company(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $company->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'pt_BR');
        $company->settings()->set(UserSettingsEnum::LOCALIZATION_TIMEZONE->value, 'America/Sao_Paulo');

        (new BackfillUserSettings)();

        $fresh = $user->fresh();
        $this->assertSame('pt_BR', $fresh->settings()->get(UserSettingsEnum::LOCALIZATION_LOCALE->value));
        $this->assertSame('America/Sao_Paulo', $fresh->settings()->get(UserSettingsEnum::LOCALIZATION_TIMEZONE->value));
    }

    public function test_uses_the_oldest_owned_company_when_user_owns_several(): void
    {
        $user = User::factory()->create();
        $companyA = $user->companies()->wherePivotNotNull('joined_at')->firstOrFail(); // mais antiga
        $companyB = (new CompanyService)->create($user);                                // mais nova

        $companyA->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'pt_BR');
        $companyB->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'es');

        (new BackfillUserSettings)();

        $this->assertSame('pt_BR', $user->fresh()->settings()->get(UserSettingsEnum::LOCALIZATION_LOCALE->value));
    }

    public function test_does_not_backfill_a_user_who_owns_no_company(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $company->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'pt_BR');

        // Membro convidado: sem empresa própria (guard da Fase 3).
        $member = new User(['name' => 'Guest', 'email' => 'guest@x.com', 'password' => 'secret-pass']);
        $member->skipCompanyProvisioning = true;
        $member->save();
        $company->users()->attach($member->id, ['joined_at' => now()]);

        (new BackfillUserSettings)();

        $this->assertNull($member->fresh()->settings()->get(UserSettingsEnum::LOCALIZATION_LOCALE->value));
    }

    public function test_does_not_overwrite_a_setting_the_user_already_has(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        $user->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'en');   // já tem
        $company->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'pt_BR');

        (new BackfillUserSettings)();

        $this->assertSame('en', $user->fresh()->settings()->get(UserSettingsEnum::LOCALIZATION_LOCALE->value));
    }
}
