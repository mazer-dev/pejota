<?php

namespace Tests\Feature;

use App\Filament\App\Resources\WhatsappConversationResource;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappConversationResourceFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_options_are_limited_to_selected_client(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $client = Client::create(['name' => 'Vivianne', 'company_id' => $user->company->id]);
        $otherClient = Client::create(['name' => 'Other', 'company_id' => $user->company->id]);

        $firstProject = Project::create([
            'name' => 'Configuração de E-mails no HubSpot',
            'company_id' => $user->company->id,
            'client_id' => $client->id,
        ]);

        $secondProject = Project::create([
            'name' => 'Novo fluxo HubSpot',
            'company_id' => $user->company->id,
            'client_id' => $client->id,
        ]);

        $otherProject = Project::create([
            'name' => 'Projeto de outro cliente',
            'company_id' => $user->company->id,
            'client_id' => $otherClient->id,
        ]);

        $options = WhatsappConversationResource::projectOptions($client->id);

        $this->assertSame([
            $firstProject->id => 'Configuração de E-mails no HubSpot',
            $secondProject->id => 'Novo fluxo HubSpot',
        ], $options);
        $this->assertArrayNotHasKey($otherProject->id, $options);
    }

    public function test_conversation_data_uses_client_phone_to_build_remote_jid(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $client = Client::create([
            'name' => 'Vivianne',
            'phone' => '+55 62 98174-9881',
            'company_id' => $user->company->id,
        ]);

        $data = WhatsappConversationResource::prepareConversationData([
            'client_id' => $client->id,
            'push_name' => null,
            'phone_number' => null,
            'remote_jid' => null,
        ]);

        $this->assertSame('Vivianne', $data['push_name']);
        $this->assertSame('+55 62 98174-9881', $data['phone_number']);
        $this->assertSame('5562981749881@s.whatsapp.net', $data['remote_jid']);
    }
}
