<?php

namespace Tests\Feature\Assistant;

use App\Enums\CompanySettingsEnum;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Client;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\AssistantChatService;
use Carbon\Carbon;
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

    public function test_it_instructs_the_model_to_use_local_time_for_database_timestamps(): void
    {
        $this->travelTo(Carbon::parse('2026-07-09 18:11:00', 'America/Sao_Paulo'));

        $user = User::factory()->create();
        $user->company->settings()->set(CompanySettingsEnum::LOCALIZATION_TIMEZONE->value, 'America/Sao_Paulo');

        $this->actingAs($user);

        $conversation = $this->makeConversation($user, 'Oi, tudo bem?');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt): bool {
                return str_contains($prompt, '09/07/2026 18:11')
                    && str_contains($prompt, 'fuso America/Sao_Paulo, UTC-03:00')
                    && str_contains($prompt, 'sent_at, created_at, updated_at')
                    && str_contains($prompt, "datetime(coluna, '-03:00')")
                    && str_contains($prompt, 'Nunca apresente o valor UTC cru');
            }))
            ->andReturn('{"say": "Vou usar horário local."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Vou usar horário local.', $answer);
    }

    public function test_it_requires_a_query_before_answering_data_questions_and_omits_old_assistant_answers(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conversation = $this->makeConversation($user, 'Eu cobrei o Felipe hoje? Veja minha conversa com ele');
        $conversation->messages()->create([
            'company_id' => $user->company->id,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => 'Resposta antiga errada dizendo 19:18.',
        ]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => ! str_contains($prompt, '19:18')))
            ->andReturn('{"say": "Sim, você cobrou às 19:18."}');
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Você tentou responder sem consultar o banco')))
            ->andReturn('{"query": "SELECT 1 AS ok"}');
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, '"ok":1')))
            ->andReturn('{"say": "Agora respondi depois de consultar."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Agora respondi depois de consultar.', $answer);
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

    public function test_it_converts_query_timestamps_to_the_user_timezone_before_feeding_results_back(): void
    {
        $user = User::factory()->create();
        $user->company->settings()->set(CompanySettingsEnum::LOCALIZATION_TIMEZONE->value, 'America/Sao_Paulo');

        $this->actingAs($user);
        $companyId = $user->company->id;

        $whatsappConversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '558199116613@s.whatsapp.net',
            'phone_number' => '558199116613',
            'push_name' => 'Felipe Franca',
            'status' => 'open',
        ]);

        WhatsappMessage::create([
            'company_id' => $companyId,
            'whatsapp_conversation_id' => $whatsappConversation->id,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_message_id' => 'UTC1',
            'remote_jid' => '558199116613@s.whatsapp.net',
            'from_me' => true,
            'message_type' => 'conversation',
            'text' => 'Cobrei a pendencia financeira.',
            'sent_at' => Carbon::parse('2026-07-09 19:18:27', 'UTC'),
        ]);

        $conversation = $this->makeConversation($user, 'Eu cobrei o Felipe hoje?');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn("{\"query\": \"SELECT sent_at, text FROM whatsapp_messages WHERE company_id = {$companyId}\"}");
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(function (string $prompt): bool {
                $result = substr($prompt, strpos($prompt, 'Resultado da consulta') ?: 0);

                return str_contains($result, 'convertidos de UTC para America/Sao_Paulo')
                    && str_contains($result, '2026-07-09 16:18:27 America/Sao_Paulo')
                    && ! str_contains($result, '"sent_at":"2026-07-09 19:18:27"');
            }))
            ->andReturn('{"say": "Você cobrou às 16:18."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Você cobrou às 16:18.', $answer);
    }

    public function test_it_rejects_timestamp_formatting_without_timezone_conversion(): void
    {
        $user = User::factory()->create();
        $user->company->settings()->set(CompanySettingsEnum::LOCALIZATION_TIMEZONE->value, 'America/Sao_Paulo');

        $this->actingAs($user);

        $conversation = $this->makeConversation($user, 'Veja minha conversa com Felipe hoje');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn('{"query": "SELECT strftime(\'%H:%M\', sent_at) AS hora FROM whatsapp_messages WHERE company_id = '.$user->company->id.'"}');
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Consulta rejeitada: a consulta formatou data/hora de coluna UTC sem converter para America/Sao_Paulo')))
            ->andReturn('{"query": "SELECT sent_at FROM whatsapp_messages WHERE company_id = '.$user->company->id.'"}');
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'A consulta não retornou linhas.')))
            ->andReturn('{"say": "Vou refazer com conversão."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Vou refazer com conversão.', $answer);
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
