<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\WhatsappConversation;
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
}
