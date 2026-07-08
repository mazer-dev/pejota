<?php

namespace Tests\Feature\Ai\Context;

use App\Models\Client;
use App\Models\ClientAiAnalysis;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ai\Context\ClientContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_suggestion_includes_facts_style_history_and_saved_analysis(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne', 'ai_context' => 'Veio da 99freelas.']);

        $status = Status::create([
            'name' => 'A Fazer', 'phase' => 'todo', 'color' => '#000', 'sort_order' => 1, 'active' => true, 'company_id' => $companyId,
        ]);
        Task::create([
            'title' => 'Confirmar escopo', 'status_id' => $status->id, 'company_id' => $companyId,
            'client_id' => $client->id, 'due_date' => now()->subDay()->toDateString(),
        ]);

        ClientAiAnalysis::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'content' => 'Temperatura: morna.',
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'evolution_instance' => 'inst',
            'remote_jid' => 'a@s.whatsapp.net', 'status' => 'open',
        ]);

        WhatsappMessage::create([
            'company_id' => $companyId, 'whatsapp_conversation_id' => $conversation->id, 'client_id' => $client->id,
            'evolution_instance' => 'inst', 'remote_message_id' => 'M1', 'remote_jid' => 'a@s.whatsapp.net',
            'from_me' => true, 'message_type' => 'chat', 'text' => 'Já te retorno.', 'sent_at' => now(),
        ]);

        $context = app(ClientContextBuilder::class)->forSuggestion($conversation);

        $this->assertStringContainsString('Veio da 99freelas.', $context);
        $this->assertStringContainsString('Confirmar escopo', $context);
        $this->assertStringContainsString('ATRASADA', $context);
        $this->assertStringContainsString('Estilo de escrita do Luiz', $context);
        $this->assertStringContainsString('Já te retorno.', $context);
        $this->assertStringContainsString('Temperatura: morna.', $context);
    }

    public function test_for_analysis_omits_the_previous_analysis_but_keeps_full_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        ClientAiAnalysis::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'content' => 'Análise anterior única.',
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'evolution_instance' => 'inst',
            'remote_jid' => 'a@s.whatsapp.net', 'status' => 'open',
        ]);

        WhatsappMessage::create([
            'company_id' => $companyId, 'whatsapp_conversation_id' => $conversation->id, 'client_id' => $client->id,
            'evolution_instance' => 'inst', 'remote_message_id' => 'M1', 'remote_jid' => 'a@s.whatsapp.net',
            'from_me' => false, 'message_type' => 'chat', 'text' => 'Preciso de um retorno urgente.', 'sent_at' => now(),
        ]);

        $context = app(ClientContextBuilder::class)->forAnalysis($client);

        $this->assertStringNotContainsString('Análise anterior única.', $context);
        $this->assertStringContainsString('Preciso de um retorno urgente.', $context);
    }
}
