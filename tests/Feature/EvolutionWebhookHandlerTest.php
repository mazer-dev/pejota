<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Evolution\EvolutionWebhookHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvolutionWebhookHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_message_links_client_and_refreshes_context_tokens(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.default_company_id' => $companyId,
            'services.evolution.instance' => 'geolead_funnel_2',
        ]);

        $client = Client::create([
            'company_id' => $companyId,
            'name' => 'Cliente WhatsApp',
            'phone' => '(11) 99999-0000',
            'ai_context' => 'Cliente veio da 99freelas.',
        ]);

        app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '5511999990000@s.whatsapp.net',
            'date_time' => now()->toISOString(),
            'data' => [
                'key' => [
                    'remoteJid' => '5511999990000@s.whatsapp.net',
                    'id' => 'ABC123',
                    'fromMe' => false,
                ],
                'pushName' => 'Cliente WhatsApp',
                'messageType' => 'conversation',
                'messageTimestamp' => now()->timestamp,
                'message' => [
                    'conversation' => 'Oi, tudo bem?',
                ],
            ],
        ]);

        $conversation = WhatsappConversation::allTenants()->first();

        $this->assertNotNull($conversation);
        $this->assertSame($client->id, $conversation->client_id);
        $this->assertSame('5511999990000', $conversation->phone_number);
        $this->assertGreaterThan(0, $conversation->context_tokens);
        $this->assertDatabaseHas('whatsapp_messages', [
            'remote_message_id' => 'ABC123',
            'text' => 'Oi, tudo bem?',
        ]);
    }

    public function test_from_me_messages_do_not_rename_the_conversation(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.default_company_id' => $companyId,
            'services.evolution.instance' => 'geolead_funnel_2',
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'push_name' => 'Vivianne',
            'status' => 'open',
        ]);

        app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '5511999990000@s.whatsapp.net',
            'date_time' => now()->toISOString(),
            'data' => [
                'key' => [
                    'remoteJid' => '5511999990000@s.whatsapp.net',
                    'id' => 'MINE1',
                    'fromMe' => true,
                ],
                'pushName' => 'Luiz Fernando',
                'messageType' => 'conversation',
                'messageTimestamp' => now()->timestamp,
                'message' => [
                    'conversation' => 'Bom dia!',
                ],
            ],
        ]);

        $this->assertSame('Vivianne', $conversation->refresh()->push_name);
        $this->assertDatabaseHas('whatsapp_messages', [
            'remote_message_id' => 'MINE1',
            'sender_name' => 'Luiz Fernando',
        ]);
    }

    public function test_each_message_stores_only_its_own_record_in_the_payload(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.default_company_id' => $companyId,
            'services.evolution.instance' => 'geolead_funnel_2',
        ]);

        app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '5511999990000@s.whatsapp.net',
            'date_time' => now()->toISOString(),
            'data' => [
                [
                    'key' => [
                        'remoteJid' => '5511999990000@s.whatsapp.net',
                        'id' => 'BATCH1',
                        'fromMe' => false,
                    ],
                    'pushName' => 'Cliente',
                    'messageType' => 'conversation',
                    'messageTimestamp' => now()->timestamp,
                    'message' => ['conversation' => 'Primeira'],
                ],
                [
                    'key' => [
                        'remoteJid' => '5511999990000@s.whatsapp.net',
                        'id' => 'BATCH2',
                        'fromMe' => false,
                    ],
                    'pushName' => 'Cliente',
                    'messageType' => 'conversation',
                    'messageTimestamp' => now()->timestamp,
                    'message' => ['conversation' => 'Segunda'],
                ],
            ],
        ], dispatchSuggestions: false);

        $first = WhatsappMessage::allTenants()->where('remote_message_id', 'BATCH1')->firstOrFail();
        $second = WhatsappMessage::allTenants()->where('remote_message_id', 'BATCH2')->firstOrFail();

        $this->assertSame('BATCH1', data_get($first->payload, 'data.key.id'));
        $this->assertSame('BATCH2', data_get($second->payload, 'data.key.id'));
        $this->assertFalse(array_is_list($first->payload['data']));
    }
}
