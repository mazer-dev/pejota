<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class ClientContactTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Landlord::addTenant('company_id', $this->user->company->id);
    }

    private function makeClient(): Client
    {
        return Client::create(['name' => 'Acme', 'company_id' => $this->user->company->id]);
    }

    public function test_client_has_many_contacts(): void
    {
        $client = $this->makeClient();
        $contact = $client->contacts()->create([
            'name' => 'Finance',
            'email' => 'fin@acme.test',
            'receives_billing' => true,
        ]);

        $this->assertInstanceOf(ClientContact::class, $client->fresh()->contacts->first());
        $this->assertSame($contact->id, $client->fresh()->contacts->first()->id);
        $this->assertTrue($contact->fresh()->receives_billing);
    }

    public function test_client_billing_channel_columns_cast_to_boolean(): void
    {
        $client = Client::create([
            'name' => 'Acme',
            'company_id' => $this->user->company->id,
            'bill_by_email' => 1,
            'bill_by_whatsapp' => 0,
        ]);

        $client = $client->fresh();
        $this->assertTrue($client->bill_by_email);
        $this->assertFalse($client->bill_by_whatsapp);
    }

    public function test_contact_is_scoped_to_tenant(): void
    {
        $client = $this->makeClient();
        $client->contacts()->create(['name' => 'A', 'email' => 'a@acme.test']);

        $other = User::factory()->create();
        Landlord::addTenant('company_id', $other->company->id);

        $this->assertSame(0, ClientContact::query()->count());
    }
}
