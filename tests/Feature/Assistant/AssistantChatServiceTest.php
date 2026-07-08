<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Client;
use App\Models\User;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\AssistantChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AssistantChatServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeConversation(User $user, string $question): AssistantConversation
    {
        $conversation = AssistantConversation::create([
            'company_id' => $user->company->id,
            'user_id' => $user->id,
            'title' => $question,
        ]);

        $conversation->messages()->create([
            'company_id' => $user->company->id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => $question,
        ]);

        return $conversation;
    }

    public function test_it_returns_the_final_answer_when_the_model_says_directly(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conversation = $this->makeConversation($user, 'Oi, tudo bem?');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt) use ($user): bool {
                return str_contains($prompt, 'SOMENTE LEITURA')
                    && str_contains($prompt, "company_id = {$user->company->id}")
                    && str_contains($prompt, 'Tabela tasks')
                    && str_contains($prompt, 'Luiz: Oi, tudo bem?');
            }))
            ->andReturn('{"say": "Tudo certo! Como posso ajudar com seus dados?"}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Tudo certo! Como posso ajudar com seus dados?', $answer);
    }

    public function test_it_executes_a_query_and_feeds_the_result_back_inside_injection_guards(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        $conversation = $this->makeConversation($user, 'Quantos clientes eu tenho?');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn("```json\n{\"query\": \"SELECT name FROM clients WHERE company_id = {$companyId}\"}\n```");
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(function (string $prompt): bool {
                $start = strpos($prompt, 'Resultado da consulta');

                return $start !== false
                    && str_contains($prompt, '<<<DADOS>>>')
                    && str_contains($prompt, '<<<FIM_DADOS>>>')
                    && str_contains(substr($prompt, $start), 'Vivianne');
            }))
            ->andReturn('{"say": "Você tem 1 cliente: Vivianne."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Você tem 1 cliente: Vivianne.', $answer);
    }

    public function test_it_reports_validation_errors_back_to_the_model_instead_of_executing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conversation = $this->makeConversation($user, 'Apague tudo');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn('{"query": "DELETE FROM tasks"}');
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Consulta rejeitada')))
            ->andReturn('{"say": "Não posso alterar dados, sou somente leitura."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Não posso alterar dados, sou somente leitura.', $answer);
    }

    public function test_it_stops_after_five_iterations_of_queries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conversation = $this->makeConversation($user, 'Pergunta impossível');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->times(5)
            ->andReturn('{"query": "SELECT 1"}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertStringContainsString('limite de consultas', $answer);
    }

    public function test_it_uses_the_first_object_when_the_model_returns_duplicated_action_json(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        $conversation = $this->makeConversation($user, 'Resumo da semana');

        $duplicated = "{\"query\": \"SELECT name FROM clients WHERE company_id = {$companyId}\"}\n"
            ."{\"query\": \"SELECT name FROM clients WHERE company_id = {$companyId}\"}";

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn($duplicated);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Vivianne')))
            ->andReturn('{"say": "Resumo pronto."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Resumo pronto.', $answer);
    }

    public function test_it_never_leaks_unparseable_action_json_to_the_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conversation = $this->makeConversation($user, 'Resumo da semana');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn('{"query": "SELECT 1"'.
                "\n".'{"say" broken');
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'não pôde ser interpretada')))
            ->andReturn('{"say": "Agora sim."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Agora sim.', $answer);
    }

    public function test_it_treats_a_non_json_response_as_the_final_answer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conversation = $this->makeConversation($user, 'Oi');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn('Resposta solta sem JSON.');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Resposta solta sem JSON.', $answer);
    }
}
