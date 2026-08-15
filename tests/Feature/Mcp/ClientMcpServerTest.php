<?php

namespace Tests\Feature\Mcp;

use App\Models\Client;
use App\Models\ClientAiAnalysis;
use App\Models\ClientMcpToken;
use App\Models\Note;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappAttachment;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ClientMcpServerTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/mcp/client';

    private int $companyId;

    private Client $clientA;

    private Client $clientB;

    private Task $taskA;

    private Task $taskB;

    private WhatsappConversation $conversationA;

    private WhatsappConversation $conversationB;

    private string $tokenA;

    private string $tokenB;

    private string $revokedToken;

    private string $otherCompanyToken;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->companyId = (int) $user->company->id;

        $this->actingAs($user);

        $todo = Status::create([
            'company_id' => $this->companyId,
            'name' => 'A Fazer',
            'phase' => 'todo',
        ]);

        $closed = Status::create([
            'company_id' => $this->companyId,
            'name' => 'Concluído',
            'phase' => 'closed',
        ]);

        $this->clientA = Client::create([
            'company_id' => $this->companyId,
            'name' => 'Marlon Confiança',
            'email' => 'marlon@example.test',
            'phone' => '5581999990000',
            'ai_context' => 'Cliente de planilhas de comissão.',
        ]);

        $this->clientB = Client::create([
            'company_id' => $this->companyId,
            'name' => 'Cliente Secreto',
            'ai_context' => 'SEGREDO-DO-CLIENTE-B',
        ]);

        $projectA = Project::create([
            'company_id' => $this->companyId,
            'client_id' => $this->clientA->id,
            'name' => 'Planilha de comissões',
            'description' => 'Motor de cálculo',
            'ai_context' => 'Contexto do projeto A',
        ]);

        Project::create([
            'company_id' => $this->companyId,
            'client_id' => $this->clientB->id,
            'name' => 'PROJETO-SECRETO-B',
        ]);

        $this->taskA = Task::create([
            'company_id' => $this->companyId,
            'client_id' => $this->clientA->id,
            'project_id' => $projectA->id,
            'status_id' => $todo->id,
            'title' => 'Ajustar aba de garantia',
            'description' => 'Descrição completa da tarefa A',
            'priority' => 'high',
        ]);

        Task::create([
            'company_id' => $this->companyId,
            'client_id' => $this->clientA->id,
            'status_id' => $closed->id,
            'title' => 'Entregar planilha revisada',
        ]);

        $this->taskB = Task::create([
            'company_id' => $this->companyId,
            'client_id' => $this->clientB->id,
            'status_id' => $todo->id,
            'title' => 'TAREFA-SECRETA-B',
        ]);

        Note::create([
            'company_id' => $this->companyId,
            'client_id' => $this->clientA->id,
            'title' => 'Combinado da reunião',
            'content' => [
                ['type' => 'markdown', 'data' => ['content' => 'Entregar até sexta.']],
            ],
        ]);

        Note::create([
            'company_id' => $this->companyId,
            'client_id' => $this->clientB->id,
            'title' => 'NOTA-SECRETA-B',
            'content' => [
                ['type' => 'markdown', 'data' => ['content' => 'conteudo b']],
            ],
        ]);

        ClientAiAnalysis::create([
            'company_id' => $this->companyId,
            'client_id' => $this->clientA->id,
            'content' => 'Análise mais recente do cliente A.',
        ]);

        $this->conversationA = WhatsappConversation::create([
            'company_id' => $this->companyId,
            'client_id' => $this->clientA->id,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '5581999990000@s.whatsapp.net',
            'phone_number' => '5581999990000',
            'name' => 'Marlon',
            'last_message_at' => now(),
        ]);

        $this->conversationB = WhatsappConversation::create([
            'company_id' => $this->companyId,
            'client_id' => $this->clientB->id,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '5581888880000@s.whatsapp.net',
            'name' => 'CONVERSA-SECRETA-B',
            'last_message_at' => now(),
        ]);

        $firstMessage = WhatsappMessage::create([
            'company_id' => $this->companyId,
            'whatsapp_conversation_id' => $this->conversationA->id,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_message_id' => 'AAA1',
            'from_me' => false,
            'sender_name' => 'Marlon',
            'message_type' => 'text',
            'text' => 'Bom dia, vou analisar a planilha',
            'sent_at' => now()->subHours(2),
        ]);

        $secondMessage = WhatsappMessage::create([
            'company_id' => $this->companyId,
            'whatsapp_conversation_id' => $this->conversationA->id,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_message_id' => 'AAA2',
            'from_me' => true,
            'message_type' => 'text',
            'text' => 'Show, qualquer coisa me avisa',
            'sent_at' => now()->subHour(),
        ]);

        WhatsappAttachment::create([
            'company_id' => $this->companyId,
            'whatsapp_message_id' => $secondMessage->id,
            'original_filename' => 'audio.ogg',
            'mime_type' => 'audio/ogg',
            'transcription_text' => 'Transcrição do áudio enviado.',
        ]);

        WhatsappMessage::create([
            'company_id' => $this->companyId,
            'whatsapp_conversation_id' => $this->conversationB->id,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_message_id' => 'BBB1',
            'from_me' => false,
            'message_type' => 'text',
            'text' => 'MENSAGEM-SECRETA-B',
            'sent_at' => now(),
        ]);

        $this->tokenA = $this->issueToken($this->clientA, 'Claude Code');
        $this->tokenB = $this->issueToken($this->clientB, 'Claude Code');
        $this->revokedToken = $this->issueToken($this->clientA, 'Antigo', revoked: true);

        $otherUser = User::factory()->create();
        $otherClient = Client::create([
            'company_id' => $otherUser->company->id,
            'name' => 'Cliente de outra empresa',
        ]);
        $this->otherCompanyToken = $this->issueToken($otherClient, 'Outra empresa');

        Auth::logout();

        $this->assertSame($firstMessage->id + 1, $secondMessage->id);
    }

    private function issueToken(Client $client, string $name, bool $revoked = false): string
    {
        $plain = ClientMcpToken::generatePlainToken();

        ClientMcpToken::create([
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'name' => $name,
            'token_hash' => ClientMcpToken::hashToken($plain),
            'revoked_at' => $revoked ? now() : null,
        ]);

        return $plain;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function rpc(string $method, array $params = [], ?string $token = null): TestResponse
    {
        $headers = $token === null ? [] : ['Authorization' => 'Bearer '.$token];

        return $this->postJson(self::ENDPOINT, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ], $headers);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function callTool(string $tool, array $arguments = [], ?string $token = null): array
    {
        $response = $this->rpc('tools/call', [
            'name' => $tool,
            'arguments' => $arguments,
        ], $token ?? $this->tokenA);

        $response->assertOk();

        return json_decode((string) $response->json('result.content.0.text'), true) ?? [];
    }

    public function test_it_rejects_a_request_without_a_token(): void
    {
        $this->rpc('tools/list')
            ->assertStatus(401)
            ->assertJsonPath('error.code', -32001);
    }

    public function test_it_rejects_an_unknown_token(): void
    {
        $this->rpc('tools/list', token: 'pjm_naoexiste')->assertStatus(401);
    }

    public function test_it_rejects_a_revoked_token(): void
    {
        $this->rpc('tools/list', token: $this->revokedToken)->assertStatus(401);
    }

    public function test_it_accepts_the_token_on_the_alternative_header(): void
    {
        $this->postJson(self::ENDPOINT, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ], ['X-Pejota-Mcp-Token' => $this->tokenA])->assertOk();
    }

    public function test_it_exposes_only_read_tools(): void
    {
        $response = $this->rpc('tools/list', token: $this->tokenA)->assertOk();

        $names = collect($response->json('result.tools'))->pluck('name')->sort()->values()->all();

        $this->assertSame([
            'client_overview',
            'get_task',
            'list_notes',
            'list_projects',
            'list_tasks',
            'list_whatsapp_conversations',
            'list_whatsapp_messages',
        ], $names);
    }

    public function test_no_tool_accepts_a_client_argument(): void
    {
        $response = $this->rpc('tools/list', token: $this->tokenA)->assertOk();

        foreach ($response->json('result.tools') as $tool) {
            $properties = array_keys($tool['inputSchema']['properties'] ?? []);

            $this->assertNotContains('client_id', $properties, "A tool {$tool['name']} não pode receber client_id.");
            $this->assertNotContains('company_id', $properties, "A tool {$tool['name']} não pode receber company_id.");
        }
    }

    public function test_initialize_announces_the_client_of_the_connection(): void
    {
        $response = $this->rpc('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ], $this->tokenA)->assertOk();

        $this->assertSame('PeJota: Marlon Confiança', $response->json('result.serverInfo.name'));
        $this->assertStringContainsString('somente leitura', $response->json('result.instructions'));
        $this->assertStringContainsString('Marlon Confiança', $response->json('result.instructions'));
    }

    public function test_client_overview_returns_the_bound_client(): void
    {
        $data = $this->callTool('client_overview');

        $this->assertSame($this->clientA->id, $data['client']['id']);
        $this->assertSame('Marlon Confiança', $data['client']['name']);
        $this->assertSame('Cliente de planilhas de comissão.', $data['ai_context']);
        $this->assertSame('Análise mais recente do cliente A.', $data['latest_analysis']['content']);
        $this->assertSame(1, $data['summary']['projects']);
        $this->assertSame(2, $data['summary']['tasks_total']);
        $this->assertSame(1, $data['summary']['tasks_by_phase']['todo']);
        $this->assertSame(1, $data['summary']['tasks_by_phase']['closed']);
        $this->assertSame(1, $data['summary']['notes']);
        $this->assertSame(1, $data['summary']['whatsapp_conversations']);
        $this->assertSame(2, $data['summary']['whatsapp_messages']);
    }

    public function test_the_same_endpoint_returns_the_other_client_for_the_other_token(): void
    {
        $data = $this->callTool('client_overview', token: $this->tokenB);

        $this->assertSame($this->clientB->id, $data['client']['id']);
        $this->assertSame('Cliente Secreto', $data['client']['name']);
    }

    public function test_list_projects_only_returns_projects_of_the_bound_client(): void
    {
        $data = $this->callTool('list_projects');

        $this->assertSame(1, $data['total']);
        $this->assertSame('Planilha de comissões', $data['projects'][0]['name']);
        $this->assertStringNotContainsString('PROJETO-SECRETO-B', json_encode($data));
    }

    public function test_list_tasks_only_returns_tasks_of_the_bound_client(): void
    {
        $data = $this->callTool('list_tasks');

        $titles = array_column($data['tasks'], 'title');

        $this->assertCount(2, $titles);
        $this->assertContains('Ajustar aba de garantia', $titles);
        $this->assertNotContains('TAREFA-SECRETA-B', $titles);
    }

    public function test_list_tasks_filters_by_phase(): void
    {
        $data = $this->callTool('list_tasks', ['phase' => 'closed']);

        $this->assertSame(1, $data['total']);
        $this->assertSame('Entregar planilha revisada', $data['tasks'][0]['title']);
    }

    public function test_list_tasks_filters_by_search(): void
    {
        $data = $this->callTool('list_tasks', ['search' => 'garantia']);

        $this->assertSame(1, $data['total']);
        $this->assertSame('Ajustar aba de garantia', $data['tasks'][0]['title']);
    }

    public function test_list_tasks_respects_the_limit_ceiling(): void
    {
        $data = $this->callTool('list_tasks', ['limit' => 9999]);

        $this->assertLessThanOrEqual(100, $data['total']);
    }

    public function test_get_task_returns_the_full_task(): void
    {
        $data = $this->callTool('get_task', ['task_id' => $this->taskA->id]);

        $this->assertSame('Ajustar aba de garantia', $data['task']['title']);
        $this->assertSame('Descrição completa da tarefa A', $data['task']['description']);
        $this->assertSame('Planilha de comissões', $data['task']['project']);
    }

    public function test_get_task_refuses_a_task_from_another_client(): void
    {
        $response = $this->rpc('tools/call', [
            'name' => 'get_task',
            'arguments' => ['task_id' => $this->taskB->id],
        ], $this->tokenA)->assertOk();

        $this->assertTrue($response->json('result.isError'));
        $this->assertStringNotContainsString('TAREFA-SECRETA-B', (string) $response->getContent());
    }

    public function test_list_notes_only_returns_notes_of_the_bound_client(): void
    {
        $data = $this->callTool('list_notes');

        $this->assertSame(1, $data['total']);
        $this->assertSame('Combinado da reunião', $data['notes'][0]['title']);
        $this->assertSame('Entregar até sexta.', $data['notes'][0]['content']);
    }

    public function test_list_whatsapp_conversations_only_returns_conversations_of_the_bound_client(): void
    {
        $data = $this->callTool('list_whatsapp_conversations');

        $this->assertSame(1, $data['total']);
        $this->assertSame('Marlon', $data['conversations'][0]['name']);
        $this->assertSame(2, $data['conversations'][0]['messages']);
    }

    public function test_list_whatsapp_messages_returns_messages_in_chronological_order(): void
    {
        $data = $this->callTool('list_whatsapp_messages');

        $this->assertSame(2, $data['total']);
        $this->assertSame('Bom dia, vou analisar a planilha', $data['messages'][0]['text']);
        $this->assertSame('Show, qualquer coisa me avisa', $data['messages'][1]['text']);
        $this->assertFalse($data['messages'][0]['from_me']);
        $this->assertSame('Transcrição do áudio enviado.', $data['messages'][1]['attachments'][0]['transcription']);
    }

    public function test_list_whatsapp_messages_refuses_a_conversation_from_another_client(): void
    {
        $response = $this->rpc('tools/call', [
            'name' => 'list_whatsapp_messages',
            'arguments' => ['conversation_id' => $this->conversationB->id],
        ], $this->tokenA)->assertOk();

        $this->assertTrue($response->json('result.isError'));
        $this->assertStringNotContainsString('MENSAGEM-SECRETA-B', (string) $response->getContent());
    }

    public function test_list_whatsapp_messages_never_leaks_another_client_without_a_filter(): void
    {
        $response = $this->rpc('tools/call', [
            'name' => 'list_whatsapp_messages',
            'arguments' => ['limit' => 200],
        ], $this->tokenA)->assertOk();

        $this->assertStringNotContainsString('MENSAGEM-SECRETA-B', (string) $response->getContent());
    }

    public function test_a_token_from_another_company_cannot_read_this_company(): void
    {
        $data = $this->callTool('client_overview', token: $this->otherCompanyToken);

        $this->assertSame('Cliente de outra empresa', $data['client']['name']);
        $this->assertSame(0, $data['summary']['tasks_total']);
        $this->assertSame(0, $data['summary']['whatsapp_messages']);
    }

    public function test_it_records_the_last_use_of_the_token(): void
    {
        $token = ClientMcpToken::query()
            ->withoutGlobalScopes()
            ->where('token_hash', ClientMcpToken::hashToken($this->tokenA))
            ->firstOrFail();

        $this->assertNull($token->last_used_at);

        $this->callTool('client_overview');

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    public function test_an_unknown_tool_is_rejected(): void
    {
        $response = $this->rpc('tools/call', [
            'name' => 'create_task',
            'arguments' => [],
        ], $this->tokenA)->assertOk();

        $this->assertSame(-32602, $response->json('error.code'));
    }

    public function test_a_get_on_the_endpoint_is_not_allowed(): void
    {
        $this->get(self::ENDPOINT)->assertStatus(405);
    }
}
