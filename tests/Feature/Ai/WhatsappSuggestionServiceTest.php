<?php

namespace Tests\Feature\Ai;

use App\Enums\WhatsappSuggestionStatusEnum;
use App\Enums\WhatsappSuggestionTypeEnum;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSuggestion;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\Context\PromptGuard;
use App\Services\Ai\WhatsappSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsappSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Project $project;

    private WhatsappConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $companyId = $this->user->company->id;

        $this->client = Client::create([
            'company_id' => $companyId,
            'name' => 'Vivianne',
        ]);

        $this->project = Project::create([
            'company_id' => $companyId,
            'client_id' => $this->client->id,
            'name' => 'Configuração de E-mails',
        ]);

        $this->conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'evolution_instance' => 'inst',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'status' => 'open',
        ]);
    }

    private function makeMessage(string $text): WhatsappMessage
    {
        return WhatsappMessage::create([
            'company_id' => $this->user->company->id,
            'whatsapp_conversation_id' => $this->conversation->id,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'evolution_instance' => 'inst',
            'remote_message_id' => 'MSG-'.fake()->unique()->uuid(),
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'sender_name' => 'Vivianne',
            'from_me' => false,
            'message_type' => 'text',
            'text' => $text,
            'sent_at' => now(),
        ]);
    }

    private function mockRunner(string $response, ?callable $promptAssertion = null): void
    {
        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt) use ($promptAssertion): bool {
                return $promptAssertion === null || $promptAssertion($prompt);
            }))
            ->andReturn($response);

        $this->instance(AiCliRunner::class, $runner);
    }

    public function test_it_creates_pending_suggestions_from_the_ai_json(): void
    {
        $message = $this->makeMessage('Segue a senha do painel: hunter2. E precisamos de mais 5 e-mails na cadência.');

        $this->mockRunner(
            "```json\n[\n{\"type\": \"note\", \"title\": \"Credencial do painel\", \"content\": \"Senha do painel: hunter2.\"},\n{\"type\": \"task\", \"title\": \"Criar mais 5 e-mails\", \"content\": \"Cliente pediu mais 5 e-mails na cadência.\"}\n]\n```",
            fn (string $prompt): bool => str_contains($prompt, PromptGuard::START)
                && str_contains($prompt, 'Segue a senha do painel')
                && str_contains($prompt, 'Mensagens novas desde a última análise')
                && str_contains($prompt, 'responda exatamente []'),
        );

        $created = app(WhatsappSuggestionService::class)
            ->analyze($this->conversation, collect([$message]), $message);

        $this->assertCount(2, $created);
        $this->assertDatabaseCount('whatsapp_suggestions', 2);

        $note = WhatsappSuggestion::query()->where('type', WhatsappSuggestionTypeEnum::Note)->first();
        $this->assertNotNull($note);
        $this->assertSame('Credencial do painel', $note->title);
        $this->assertSame('Senha do painel: hunter2.', $note->content);
        $this->assertSame(WhatsappSuggestionStatusEnum::Pending, $note->status);
        $this->assertSame($this->conversation->id, $note->whatsapp_conversation_id);
        $this->assertSame($message->id, $note->whatsapp_message_id);
        $this->assertSame($this->client->id, $note->client_id);
        $this->assertSame($this->project->id, $note->project_id);

        $task = WhatsappSuggestion::query()->where('type', WhatsappSuggestionTypeEnum::Task)->first();
        $this->assertNotNull($task);
        $this->assertSame('Criar mais 5 e-mails', $task->title);
    }

    public function test_it_creates_nothing_when_the_ai_returns_an_empty_array(): void
    {
        $message = $this->makeMessage('Bom dia! Tudo bem?');

        $this->mockRunner('[]');

        $created = app(WhatsappSuggestionService::class)
            ->analyze($this->conversation, collect([$message]), $message);

        $this->assertCount(0, $created);
        $this->assertDatabaseCount('whatsapp_suggestions', 0);
    }

    public function test_it_parses_json_surrounded_by_prose(): void
    {
        $message = $this->makeMessage('Pode ajustar o formulário para aceitar CNPJ?');

        $this->mockRunner(
            "Aqui estão as sugestões:\n[{\"type\": \"task\", \"title\": \"Ajustar formulário para CNPJ\", \"content\": \"Cliente pediu suporte a CNPJ.\"}]\nEspero ter ajudado.",
        );

        $created = app(WhatsappSuggestionService::class)
            ->analyze($this->conversation, collect([$message]), $message);

        $this->assertCount(1, $created);
        $this->assertSame('Ajustar formulário para CNPJ', $created->first()->title);
    }

    public function test_it_ignores_items_without_valid_type_or_title(): void
    {
        $message = $this->makeMessage('Mensagem qualquer.');

        $this->mockRunner(json_encode([
            ['type' => 'invoice', 'title' => 'Tipo inválido', 'content' => 'x'],
            ['type' => 'task', 'title' => '', 'content' => 'sem título'],
            ['type' => 'note', 'content' => 'sem chave de título'],
            ['type' => 'task', 'title' => 'Único válido', 'content' => 'ok'],
        ]));

        $created = app(WhatsappSuggestionService::class)
            ->analyze($this->conversation, collect([$message]), $message);

        $this->assertCount(1, $created);
        $this->assertSame('Único válido', $created->first()->title);
    }

    public function test_it_skips_titles_already_pending_or_accepted_in_the_conversation(): void
    {
        WhatsappSuggestion::create([
            'company_id' => $this->user->company->id,
            'whatsapp_conversation_id' => $this->conversation->id,
            'type' => WhatsappSuggestionTypeEnum::Task,
            'title' => 'Criar cadência de e-mails',
            'content' => 'Já sugerido antes.',
            'status' => WhatsappSuggestionStatusEnum::Pending,
        ]);

        $message = $this->makeMessage('Confirmando a cadência de e-mails e o novo relatório.');

        $this->mockRunner(json_encode([
            ['type' => 'task', 'title' => 'criar Cadência de e-mails!', 'content' => 'Duplicada.'],
            ['type' => 'task', 'title' => 'Montar novo relatório', 'content' => 'Nova.'],
        ]));

        $created = app(WhatsappSuggestionService::class)
            ->analyze($this->conversation, collect([$message]), $message);

        $this->assertCount(1, $created);
        $this->assertSame('Montar novo relatório', $created->first()->title);
        $this->assertDatabaseCount('whatsapp_suggestions', 2);
    }

    public function test_it_deduplicates_titles_within_the_same_response(): void
    {
        $message = $this->makeMessage('Preciso do acesso ao servidor.');

        $this->mockRunner(json_encode([
            ['type' => 'note', 'title' => 'Acesso ao servidor', 'content' => 'Primeira.'],
            ['type' => 'note', 'title' => 'ACESSO AO SERVIDOR', 'content' => 'Repetida.'],
        ]));

        $created = app(WhatsappSuggestionService::class)
            ->analyze($this->conversation, collect([$message]), $message);

        $this->assertCount(1, $created);
    }

    public function test_it_calls_no_ai_without_new_messages(): void
    {
        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldNotReceive('complete');
        $this->instance(AiCliRunner::class, $runner);

        $created = app(WhatsappSuggestionService::class)
            ->analyze($this->conversation, collect());

        $this->assertCount(0, $created);
        $this->assertDatabaseCount('whatsapp_suggestions', 0);
    }
}
