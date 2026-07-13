<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\AssistantChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Covers the two-sided prompt history (assistant answers included,
 * truncated, canned failures filtered, passphrase redacted, total capped)
 * and the forced-say final iteration of the respond() loop.
 */
class AssistantChatServiceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeConversation(): AssistantConversation
    {
        return AssistantConversation::create([
            'company_id' => $this->user->company->id,
            'user_id' => $this->user->id,
            'title' => 'Teste',
        ]);
    }

    private function addMessage(AssistantConversation $conversation, string $role, string $content): AssistantMessage
    {
        return $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'role' => $role,
            'content' => $content,
        ]);
    }

    public function test_history_includes_truncated_assistant_answers_in_chronological_order_and_filters_the_canned_fallback(): void
    {
        $conversation = $this->makeConversation();

        $longAnalysis = 'Análise de precificação do projeto: '.str_repeat('detalhe ', 120).'MARCADOR_ALEM_DO_LIMITE';

        $this->addMessage($conversation, AssistantMessage::ROLE_USER, 'Quanto devo cobrar pelo projeto do Miro?');
        $this->addMessage($conversation, AssistantMessage::ROLE_ASSISTANT, $longAnalysis);
        $this->addMessage($conversation, AssistantMessage::ROLE_ASSISTANT, 'Não consegui chegar a uma resposta dentro do limite de consultas. Tente reformular a pergunta de forma mais específica.');
        $this->addMessage($conversation, AssistantMessage::ROLE_USER, 'A questão do WABA já está pago, o Miro é escopo novo.');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt): bool {
                $firstUser = strpos($prompt, 'Luiz: Quanto devo cobrar pelo projeto do Miro?');
                $assistant = strpos($prompt, 'Assistente: Análise de precificação do projeto:');
                $secondUser = strpos($prompt, 'Luiz: A questão do WABA já está pago');

                return $firstUser !== false
                    && $assistant !== false
                    && $secondUser !== false
                    && $firstUser < $assistant
                    && $assistant < $secondUser
                    && ! str_contains($prompt, 'MARCADOR_ALEM_DO_LIMITE')
                    && ! str_contains($prompt, 'Não consegui chegar a uma resposta dentro do limite de consultas');
            }))
            ->andReturn('{"say": "Entendido: WABA pago, Miro é novo escopo."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Entendido: WABA pago, Miro é novo escopo.', $answer);
    }

    public function test_history_is_capped_at_the_configured_number_of_recent_messages(): void
    {
        config(['services.assistant.history_max_messages' => 2]);

        $conversation = $this->makeConversation();

        $this->addMessage($conversation, AssistantMessage::ROLE_USER, 'Mensagem antiga que deve sair do histórico');
        $this->addMessage($conversation, AssistantMessage::ROLE_ASSISTANT, 'Resposta antiga que deve sair do histórico');
        $this->addMessage($conversation, AssistantMessage::ROLE_ASSISTANT, 'Resposta recente que fica');
        $this->addMessage($conversation, AssistantMessage::ROLE_USER, 'Aquela nota recente continua valendo então');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt): bool {
                return str_contains($prompt, 'Assistente: Resposta recente que fica')
                    && str_contains($prompt, 'Luiz: Aquela nota recente continua valendo então')
                    && ! str_contains($prompt, 'Mensagem antiga que deve sair do histórico')
                    && ! str_contains($prompt, 'Resposta antiga que deve sair do histórico');
            }))
            ->andReturn('{"say": "Ok."}');
        $this->instance(AiCliRunner::class, $runner);

        $this->assertSame('Ok.', app(AssistantChatService::class)->respond($conversation));
    }

    public function test_history_redacts_a_pending_invoice_passphrase_from_assistant_answers(): void
    {
        $conversation = $this->makeConversation();
        $conversation->forceFill([
            'pending_action' => [
                'type' => 'create_invoice',
                'draft' => ['title' => 'Fatura X', 'client_name' => 'Felipe'],
                'passphrase' => 'Girassol',
                'expires_at' => now()->addMinutes(15)->toISOString(),
            ],
        ])->save();

        $this->addMessage($conversation, AssistantMessage::ROLE_USER, 'Cria a fatura do projeto');
        $this->addMessage($conversation, AssistantMessage::ROLE_ASSISTANT, "Confira os dados da fatura antes de confirmar:\n\nPara confirmar, digite exatamente esta palavra:\nGirassol\n\nQualquer outra mensagem NÃO cria a fatura.");
        $this->addMessage($conversation, AssistantMessage::ROLE_USER, 'Qual o total mesmo?');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt): bool {
                return str_contains($prompt, '[palavra-passe oculta]')
                    && ! str_contains($prompt, 'Girassol');
            }))
            ->andReturn('{"say": "O total é R$ 1.000,00."}');
        $this->instance(AiCliRunner::class, $runner);

        $this->assertSame('O total é R$ 1.000,00.', app(AssistantChatService::class)->respond($conversation));
    }

    public function test_the_final_iteration_prompt_carries_the_mandatory_say_instruction(): void
    {
        $conversation = $this->makeConversation();
        $this->addMessage($conversation, AssistantMessage::ROLE_USER, 'Pergunta genérica sem palavras-chave');

        $prompts = [];

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->times(5)
            ->andReturnUsing(function (string $prompt) use (&$prompts): string {
                $prompts[] = $prompt;

                return '{"query": "SELECT 1 AS ok"}';
            });
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertCount(5, $prompts);
        $this->assertStringNotContainsString('sua ÚLTIMA chance de responder', $prompts[3]);
        $this->assertStringContainsString('sua ÚLTIMA chance de responder', $prompts[4]);
        $this->assertStringContainsString('Responda OBRIGATORIAMENTE {"say"', $prompts[4]);
        $this->assertStringContainsString('limite de consultas', $answer);
    }

    public function test_a_query_on_the_final_iteration_is_not_executed_and_the_last_say_wins(): void
    {
        $conversation = $this->makeConversation();
        $this->addMessage($conversation, AssistantMessage::ROLE_USER, 'Quantas faturas vencidas eu tenho?');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn('{"say": "Resposta parcial antes de consultar."}');
        $runner->shouldReceive('complete')
            ->times(3)
            ->ordered()
            ->andReturn('{"query": "SELECT 1 AS ok"}');
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'sua ÚLTIMA chance de responder')))
            ->andReturn('{"query": "SELECT 2 AS ignorada"}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Resposta parcial antes de consultar.', $answer);
    }

    public function test_a_query_on_the_final_iteration_without_any_say_falls_back_to_the_canned_message(): void
    {
        $conversation = $this->makeConversation();
        $this->addMessage($conversation, AssistantMessage::ROLE_USER, 'Pergunta genérica sem palavras-chave');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->times(5)
            ->andReturn('{"query": "SELECT 1 AS ok"}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertStringContainsString('limite de consultas', $answer);
    }
}
