<?php

namespace Tests\Feature\Ai\Context;

use App\Models\Client;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ai\Context\ConversationHistoryRenderer;
use App\Services\Ai\Context\LuizStyleContextSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LuizStyleContextSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_without_client_or_conversation(): void
    {
        $section = new LuizStyleContextSection(new ConversationHistoryRenderer);

        $this->assertNull($section->build());
    }

    public function test_it_samples_from_me_messages_across_all_of_the_clients_conversations(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        $conversationA = WhatsappConversation::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'evolution_instance' => 'inst',
            'remote_jid' => 'a@s.whatsapp.net', 'status' => 'open',
        ]);
        $conversationB = WhatsappConversation::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'evolution_instance' => 'inst',
            'remote_jid' => 'b@s.whatsapp.net', 'status' => 'open',
        ]);

        WhatsappMessage::create([
            'company_id' => $companyId, 'whatsapp_conversation_id' => $conversationA->id, 'client_id' => $client->id,
            'evolution_instance' => 'inst', 'remote_message_id' => 'M1', 'remote_jid' => 'a@s.whatsapp.net',
            'from_me' => true, 'message_type' => 'chat', 'text' => 'Claro, já te confirmo.', 'sent_at' => now()->subMinutes(10),
        ]);
        WhatsappMessage::create([
            'company_id' => $companyId, 'whatsapp_conversation_id' => $conversationB->id, 'client_id' => $client->id,
            'evolution_instance' => 'inst', 'remote_message_id' => 'M2', 'remote_jid' => 'b@s.whatsapp.net',
            'from_me' => true, 'message_type' => 'chat', 'text' => 'Perfeito, obrigado!', 'sent_at' => now()->subMinutes(5),
        ]);
        WhatsappMessage::create([
            'company_id' => $companyId, 'whatsapp_conversation_id' => $conversationA->id, 'client_id' => $client->id,
            'evolution_instance' => 'inst', 'remote_message_id' => 'M3', 'remote_jid' => 'a@s.whatsapp.net',
            'from_me' => false, 'message_type' => 'chat', 'text' => 'Mensagem do cliente', 'sent_at' => now(),
        ]);

        $section = new LuizStyleContextSection(new ConversationHistoryRenderer);
        $context = $section->build($client);

        $this->assertNotNull($context);
        $this->assertStringContainsString('Estilo de escrita do Luiz', $context);
        $this->assertStringContainsString('Claro, já te confirmo.', $context);
        $this->assertStringContainsString('Perfeito, obrigado!', $context);
        $this->assertStringNotContainsString('Mensagem do cliente', $context);
    }
}
