<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class BillingRecipientsTest extends TestCase
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

    private function makeClient(array $attributes = []): Client
    {
        return Client::create(array_merge(['name' => 'Acme', 'company_id' => $this->company->id], $attributes));
    }

    public function test_returns_billing_contact_emails(): void
    {
        $client = $this->makeClient(['email' => 'main@acme.test']);
        $client->contacts()->create(['name' => 'Finance', 'email' => 'fin@acme.test', 'receives_billing' => true]);
        $client->contacts()->create(['name' => 'Tech', 'email' => 'tech@acme.test', 'receives_billing' => false]);
        $client->contacts()->create(['name' => 'NoEmail', 'email' => null, 'receives_billing' => true]);

        $this->assertSame(['fin@acme.test'], $client->billingEmailRecipients());
    }

    public function test_falls_back_to_client_email_when_no_billing_contact(): void
    {
        $client = $this->makeClient(['email' => 'main@acme.test']);
        $client->contacts()->create(['name' => 'Tech', 'email' => 'tech@acme.test', 'receives_billing' => false]);

        $this->assertSame(['main@acme.test'], $client->billingEmailRecipients());
    }

    public function test_returns_empty_when_no_billing_contact_and_no_client_email(): void
    {
        $client = $this->makeClient(['email' => null]);

        $this->assertSame([], $client->billingEmailRecipients());
    }

    public function test_whatsapp_recipients_returns_billing_contact_numbers(): void
    {
        $client = $this->makeClient();
        $client->contacts()->create(['name' => 'Finance', 'whatsapp' => '+5511999998888', 'receives_billing' => true]);
        $client->contacts()->create(['name' => 'Tech', 'whatsapp' => '+5511000000000', 'receives_billing' => false]);

        $this->assertSame(['+5511999998888'], $client->billingWhatsappRecipients());
    }

    public function test_deduplicates_recipient_emails(): void
    {
        $client = $this->makeClient(['email' => 'main@acme.test']);
        $client->contacts()->create(['name' => 'A', 'email' => 'dup@acme.test', 'receives_billing' => true]);
        $client->contacts()->create(['name' => 'B', 'email' => 'dup@acme.test', 'receives_billing' => true]);

        $this->assertSame(['dup@acme.test'], $client->billingEmailRecipients());
    }
}
