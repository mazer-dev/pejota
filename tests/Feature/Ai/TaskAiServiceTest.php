<?php

namespace Tests\Feature\Ai;

use App\Models\Client;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\TaskAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TaskAiServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(User $user, array $attributes = []): Task
    {
        $status = Status::create([
            'name' => 'A Fazer', 'phase' => 'todo', 'color' => '#000', 'sort_order' => 1, 'active' => true, 'company_id' => $user->company->id,
        ]);

        return Task::create(array_merge([
            'title' => 'Configurar cobranças recorrentes',
            'status_id' => $status->id,
            'company_id' => $user->company->id,
        ], $attributes));
    }

    public function test_summary_for_client_wraps_context_in_injection_guards(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = $this->makeTask($user);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Configurar cobranças recorrentes')
                && str_contains($prompt, '<<<DADOS>>>')
                && str_contains($prompt, '<<<FIM_DADOS>>>')
                && str_contains($prompt, 'resumo de status')))
            ->andReturn('Estamos configurando as cobranças recorrentes, previsão de conclusão nesta semana.');
        $this->instance(AiCliRunner::class, $runner);

        $summary = app(TaskAiService::class)->summaryForClient($task);

        $this->assertSame('Estamos configurando as cobranças recorrentes, previsão de conclusão nesta semana.', $summary);
    }

    public function test_suggest_subtasks_parses_plain_json(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $this->makeTask($user);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn(
            '[{"title": "Configurar gateway", "description": "Integrar com o provedor"}, {"title": "Testar cobrança", "description": ""}]'
        );
        $this->instance(AiCliRunner::class, $runner);

        $subtasks = app(TaskAiService::class)->suggestSubtasks($task);

        $this->assertCount(2, $subtasks);
        $this->assertSame('Configurar gateway', $subtasks[0]['title']);
        $this->assertSame('Integrar com o provedor', $subtasks[0]['description']);
        $this->assertSame(Task::AI_KIND_TECHNICAL, $subtasks[0]['kind']);
        $this->assertSame('Testar cobrança', $subtasks[1]['title']);
        $this->assertNull($subtasks[1]['description']);
        $this->assertSame(Task::AI_KIND_TECHNICAL, $subtasks[1]['kind']);
    }

    public function test_suggest_subtasks_keeps_the_communication_kind_and_defaults_unknown_kinds(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $this->makeTask($user);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn(
            '[{"title": "Pedir acesso ao HubSpot", "description": "", "kind": "comunicacao"},'
            .' {"title": "Configurar workflows", "description": "", "kind": "tecnica"},'
            .' {"title": "Kind inválido", "description": "", "kind": "banana"}]'
        );
        $this->instance(AiCliRunner::class, $runner);

        $subtasks = app(TaskAiService::class)->suggestSubtasks($task);

        $this->assertSame(Task::AI_KIND_COMMUNICATION, $subtasks[0]['kind']);
        $this->assertSame(Task::AI_KIND_TECHNICAL, $subtasks[1]['kind']);
        $this->assertSame(Task::AI_KIND_TECHNICAL, $subtasks[2]['kind']);
    }

    public function test_draft_client_message_uses_task_context_inside_injection_guards(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne', 'ai_context' => 'Prefere comunicação direta.']);
        $task = $this->makeTask($user, [
            'title' => 'Obter convite de acesso ao HubSpot',
            'client_id' => $client->id,
        ]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Obter convite de acesso ao HubSpot')
                && str_contains($prompt, 'Prefere comunicação direta.')
                && str_contains($prompt, '<<<DADOS>>>')
                && str_contains($prompt, '<<<FIM_DADOS>>>')
                && str_contains($prompt, 'destravar a tarefa')))
            ->andReturn('Oi Vivianne! Consegue me mandar o convite de acesso ao HubSpot?');
        $this->instance(AiCliRunner::class, $runner);

        $draft = app(TaskAiService::class)->draftClientMessage($task);

        $this->assertSame('Oi Vivianne! Consegue me mandar o convite de acesso ao HubSpot?', $draft);
    }

    public function test_suggest_subtasks_strips_markdown_code_fences(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $this->makeTask($user);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn(
            "```json\n[{\"title\": \"Levantar requisitos\", \"description\": null}]\n```"
        );
        $this->instance(AiCliRunner::class, $runner);

        $subtasks = app(TaskAiService::class)->suggestSubtasks($task);

        $this->assertCount(1, $subtasks);
        $this->assertSame('Levantar requisitos', $subtasks[0]['title']);
        $this->assertNull($subtasks[0]['description']);
    }

    public function test_suggest_subtasks_returns_empty_array_for_malformed_json(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $this->makeTask($user);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn('não consigo ajudar com isso');
        $this->instance(AiCliRunner::class, $runner);

        $subtasks = app(TaskAiService::class)->suggestSubtasks($task);

        $this->assertSame([], $subtasks);
    }

    public function test_suggest_description_includes_client_facts_when_client_given(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne', 'ai_context' => 'Prefere comunicação direta.']);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Configurar cobranças')
                && str_contains($prompt, 'Prefere comunicação direta.')))
            ->andReturn('Configurar o módulo de cobranças recorrentes para o cliente.');
        $this->instance(AiCliRunner::class, $runner);

        $description = app(TaskAiService::class)->suggestDescription('Configurar cobranças', $client);

        $this->assertSame('Configurar o módulo de cobranças recorrentes para o cliente.', $description);
    }
}
