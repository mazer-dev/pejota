<?php

namespace Tests\Feature\Billing;

use App\Filament\App\Resources\ClientResource\Pages\CreateClient;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class ClientBillingFormTest extends TestCase
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

    public function test_create_client_saves_billing_config_and_contacts(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'Acme',
                'bill_by_email' => true,
                'bill_by_whatsapp' => false,
                'billing_email_subject' => 'Invoice {{ invoice.number }}',
                'contacts' => [
                    ['name' => 'Finance', 'email' => 'fin@acme.test', 'whatsapp' => '+5511999998888', 'receives_billing' => true],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = Client::query()->where('name', 'Acme')->firstOrFail();
        $this->assertTrue($client->bill_by_email);
        $this->assertSame('Invoice {{ invoice.number }}', $client->billing_email_subject);
        $this->assertCount(1, $client->contacts);
        $this->assertSame('fin@acme.test', $client->contacts->first()->email);
        $this->assertTrue($client->contacts->first()->receives_billing);
    }
}
