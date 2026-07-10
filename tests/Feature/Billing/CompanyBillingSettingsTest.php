<?php

namespace Tests\Feature\Billing;

use App\Enums\CompanySettingsEnum;
use App\Filament\App\Pages\CompanySettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class CompanyBillingSettingsTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    public function test_billing_tab_saves_template_settings(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);

        Livewire::test(CompanySettings::class)
            ->set('data.'.CompanySettingsEnum::BILLING_EMAIL_SUBJECT->value, 'Invoice {{ invoice.number }}')
            ->set('data.'.CompanySettingsEnum::BILLING_EMAIL_BODY->value, '<p>Hi</p>')
            ->set('data.'.CompanySettingsEnum::BILLING_EMAIL_SIGNATURE->value, '<p>Regards</p>')
            ->set('data.'.CompanySettingsEnum::BILLING_WHATSAPP_TEMPLATE->value, 'Invoice {{ invoice.number }} attached')
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = $company->refresh()->settings();
        $this->assertSame('Invoice {{ invoice.number }}', $settings->get(CompanySettingsEnum::BILLING_EMAIL_SUBJECT->value));
        $this->assertSame('<p>Hi</p>', $settings->get(CompanySettingsEnum::BILLING_EMAIL_BODY->value));
        $this->assertSame('<p>Regards</p>', $settings->get(CompanySettingsEnum::BILLING_EMAIL_SIGNATURE->value));
        $this->assertSame('Invoice {{ invoice.number }} attached', $settings->get(CompanySettingsEnum::BILLING_WHATSAPP_TEMPLATE->value));
    }
}
