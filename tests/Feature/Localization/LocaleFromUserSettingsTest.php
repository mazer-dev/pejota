<?php

namespace Tests\Feature\Localization;

use App\Enums\UserSettingsEnum;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleFromUserSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_is_applied_from_user_settings_over_the_company(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        // Empresa em 'es', preferência do user em 'pt_BR' — o user deve vencer.
        $company->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'es');
        $user->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'pt_BR');

        $this->actingAs($user);

        $this->get(Filament::getPanel('app')->getUrl($company));

        $this->assertSame('pt_BR', app()->getLocale());
    }

    public function test_phase3_ui_strings_are_translated_in_pt_br_and_es(): void
    {
        $keys = [
            'Team', 'Invite member', 'Change role', 'Remove', 'Resend', 'Revoke',
            'Owner', 'Admin', 'Member', 'Pending invitations', 'Accept invitation',
            'Sign in to accept', 'Invitation unavailable',
        ];

        foreach (['pt_BR', 'es'] as $locale) {
            app()->setLocale($locale);

            foreach ($keys as $key) {
                $this->assertNotSame($key, __($key), "Missing {$locale} translation for '{$key}'");
            }
        }
    }
}
