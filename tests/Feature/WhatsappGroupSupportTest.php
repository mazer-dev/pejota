<?php

namespace Tests\Feature;

use App\Filament\App\Resources\WhatsappConversationResource\Pages\CreateWhatsappConversation;
use App\Jobs\SyncWhatsappConversationHistory;
use App\Models\Client;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Evolution\EvolutionApiClient;
use App\Services\Evolution\EvolutionWebhookHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class WhatsappGroupSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ingests_group_messages_for_a_registered_group_conversation(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.default_company_id' => $companyId,
            'services.evolution.instance' => 'geolead_funnel_2',
        ]);

        $client = Client::create([
            'company_id' => $companyId,
            'name' => 'Cliente do grupo',
            'phone' => '5554999999999',
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'client_id' => $client->id,
            'name' => 'Grupo do Projeto',
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '120363123456789012@g.us',
            'is_group' => true,
            'status' => 'open',
        ]);

        $handled = app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '120363123456789012@g.us',
            'date_time' => now()->toISOString(),
            'data' => [
                'key' => [
                    'remoteJid' => '120363123456789012@g.us',
                    'participant' => '5554999999999@s.whatsapp.net',
                    'id' => 'GROUP1',
                    'fromMe' => false,
                ],
                'pushName' => 'Fulano do Grupo',
                'messageType' => 'conversation',
                'messageTimestamp' => now()->timestamp,
                'message' => [
                    'conversation' => 'Bom dia pessoal!',
                ],
            ],
        ], dispatchSuggestions: false);

        $this->assertSame(1, $handled);

        $message = WhatsappMessage::allTenants()->where('remote_message_id', 'GROUP1')->firstOrFail();

        $this->assertSame($conversation->id, $message->whatsapp_conversation_id);
        $this->assertSame('5554999999999@s.whatsapp.net', $message->sender_jid);
        $this->assertSame('Fulano do Grupo', $message->sender_name);
        $this->assertSame('Bom dia pessoal!', $message->text);
        $this->assertFalse((bool) $message->from_me);
    }

    public function test_it_discards_group_messages_for_an_unknown_group(): void
    {
        $user = User::factory()->create();
        config(['services.evolution.default_company_id' => $user->company->id]);

        $handled = app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '120363999999999999@g.us',
            'data' => [
                'key' => [
                    'remoteJid' => '120363999999999999@g.us',
                    'participant' => '5554999999999@s.whatsapp.net',
                    'id' => 'UNKNOWNGROUP1',
                    'fromMe' => false,
                ],
                'pushName' => 'Desconhecido',
                'messageType' => 'conversation',
                'messageTimestamp' => now()->timestamp,
                'message' => ['conversation' => 'Mensagem de grupo não autorizado.'],
            ],
        ]);

        $this->assertSame(0, $handled);
        $this->assertDatabaseCount('whatsapp_conversations', 0);
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_one_to_one_ingestion_still_works_after_group_support(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.default_company_id' => $companyId,
            'services.evolution.instance' => 'geolead_funnel_2',
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'name' => 'Cliente 1:1',
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '5554988887777@s.whatsapp.net',
            'phone_number' => '5554988887777',
            'is_group' => false,
            'status' => 'open',
        ]);

        $handled = app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '5554988887777@s.whatsapp.net',
            'date_time' => now()->toISOString(),
            'data' => [
                'key' => [
                    'remoteJid' => '5554988887777@s.whatsapp.net',
                    'id' => 'DIRECT1',
                    'fromMe' => false,
                ],
                'pushName' => 'Cliente Direto',
                'messageType' => 'conversation',
                'messageTimestamp' => now()->timestamp,
                'message' => [
                    'conversation' => 'Oi, mensagem direta.',
                ],
            ],
        ], dispatchSuggestions: false);

        $this->assertSame(1, $handled);

        $message = WhatsappMessage::allTenants()->where('remote_message_id', 'DIRECT1')->firstOrFail();

        $this->assertSame($conversation->id, $message->whatsapp_conversation_id);
        $this->assertSame('5554988887777@s.whatsapp.net', $message->sender_jid);
        $this->assertSame('Oi, mensagem direta.', $message->text);
        $this->assertFalse((bool) $message->from_me);
    }

    public function test_it_sends_text_to_a_group_using_the_raw_group_jid(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'fallback_instance',
        ]);

        Http::fake([
            'http://evolution.test/message/sendText/client_instance' => Http::response([
                'key' => ['id' => 'GROUPMSG1'],
            ]),
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'name' => 'Grupo do Projeto',
            'evolution_instance' => 'client_instance',
            'remote_jid' => '120363123456789012@g.us',
            'is_group' => true,
            'status' => 'open',
        ]);

        app(EvolutionApiClient::class)->sendText($conversation, 'Olá grupo');

        Http::assertSent(fn ($request) => $request->url() === 'http://evolution.test/message/sendText/client_instance'
            && $request['number'] === '120363123456789012@g.us'
            && $request['text'] === 'Olá grupo');
    }

    public function test_group_options_maps_group_jid_to_subject(): void
    {
        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
        ]);

        Http::fake([
            'http://evolution.test/group/fetchAllGroups/client_instance*' => Http::response([
                ['id' => '120363123456789012@g.us', 'subject' => 'Grupo A'],
                ['id' => '120363000000000000@g.us', 'subject' => 'Grupo B'],
                ['subject' => 'Sem id, ignorado'],
            ]),
        ]);

        $options = app(EvolutionApiClient::class)->groupOptions('client_instance');

        $this->assertSame([
            '120363123456789012@g.us' => 'Grupo A',
            '120363000000000000@g.us' => 'Grupo B',
        ], $options);
    }

    public function test_group_options_returns_empty_array_on_request_failure(): void
    {
        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
        ]);

        Http::fake([
            'http://evolution.test/group/fetchAllGroups/client_instance*' => Http::response('boom', 500),
        ]);

        $this->assertSame([], app(EvolutionApiClient::class)->groupOptions('client_instance'));
    }

    public function test_creating_a_group_conversation_requires_a_client(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Landlord::addTenant('company_id', $user->company->id);

        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'client_instance',
        ]);

        Http::fake([
            'http://evolution.test/instance/fetchInstances' => Http::response([]),
            'http://evolution.test/group/fetchAllGroups/*' => Http::response([
                ['id' => '120363123456789012@g.us', 'subject' => 'Grupo A'],
            ]),
        ]);

        Livewire::test(CreateWhatsappConversation::class)
            ->fillForm([
                'is_group' => true,
                'name' => 'Grupo sem cliente',
                'remote_jid' => '120363123456789012@g.us',
                'evolution_instance' => 'client_instance',
                'status' => 'open',
            ])
            ->call('create')
            ->assertHasFormErrors(['client_id']);
    }

    public function test_creating_a_group_conversation_persists_is_group_and_group_jid(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Landlord::addTenant('company_id', $user->company->id);

        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'client_instance',
        ]);

        Http::fake([
            'http://evolution.test/instance/fetchInstances' => Http::response([]),
            'http://evolution.test/group/fetchAllGroups/*' => Http::response([
                ['id' => '120363123456789012@g.us', 'subject' => 'Grupo A'],
            ]),
        ]);

        Bus::fake([SyncWhatsappConversationHistory::class]);

        $client = Client::create([
            'name' => 'Cliente do grupo',
            'company_id' => $user->company->id,
        ]);

        Livewire::test(CreateWhatsappConversation::class)
            ->fillForm([
                'is_group' => true,
                'client_id' => $client->id,
                'name' => 'Grupo A',
                'remote_jid' => '120363123456789012@g.us',
                'evolution_instance' => 'client_instance',
                'status' => 'open',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $conversation = WhatsappConversation::allTenants()
            ->where('remote_jid', '120363123456789012@g.us')
            ->firstOrFail();

        $this->assertTrue((bool) $conversation->is_group);
        $this->assertSame($client->id, $conversation->client_id);
        $this->assertSame('120363123456789012@g.us', $conversation->remote_jid);
    }
}
