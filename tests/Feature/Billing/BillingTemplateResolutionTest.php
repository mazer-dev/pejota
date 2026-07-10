<?php

namespace Tests\Feature\Billing;

use App\Enums\CompanySettingsEnum;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class BillingTemplateResolutionTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
    }

    public function test_uses_company_default_when_client_override_blank(): void
    {
        $this->company->settings()->set(CompanySettingsEnum::BILLING_EMAIL_SUBJECT->value, 'Company subject');
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id]);

        $this->assertSame('Company subject', $client->resolvedEmailSubject());
    }

    public function test_client_override_wins_over_company_default(): void
    {
        $this->company->settings()->set(CompanySettingsEnum::BILLING_EMAIL_BODY->value, '<p>Company</p>');
        $client = Client::create([
            'name' => 'Acme',
            'company_id' => $this->company->id,
            'billing_email_body' => '<p>Client override</p>',
        ]);

        $this->assertSame('<p>Client override</p>', $client->resolvedEmailBody());
    }

    public function test_returns_null_when_neither_set(): void
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id]);

        $this->assertNull($client->resolvedEmailSignature());
        $this->assertNull($client->resolvedWhatsappTemplate());
    }

    public function test_empty_html_override_inherits_company_default(): void
    {
        $this->company->settings()->set(CompanySettingsEnum::BILLING_EMAIL_BODY->value, '<p>Company body</p>');

        $client = Client::create([
            'name' => 'Acme',
            'company_id' => $this->company->id,
            'billing_email_body' => '<p></p>',
        ]);
        $this->assertSame('<p>Company body</p>', $client->resolvedEmailBody());

        $client->update(['billing_email_body' => '<p>&nbsp;</p>']);
        $this->assertSame('<p>Company body</p>', $client->fresh()->resolvedEmailBody());
    }
}
