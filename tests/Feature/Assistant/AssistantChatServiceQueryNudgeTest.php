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
 * Covers the escape valve around the "query the database first" nudge: the
 * nudge fires at most once per response, an insisted say is accepted as
 * final, the last say seen wins over the canned fallback when the loop is
 * exhausted, and declarative/corrective messages containing data keywords
 * no longer force a query at all.
 */
class AssistantChatServiceQueryNudgeTest extends TestCase
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

    public function test_a_declarative_message_with_data_keywords_is_answered_directly_without_a_query_nudge(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conversation = $this->makeConversation($user, 'O valor de hoje foi do Felipe');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->andReturn('{"say": "Entendido, anotado: o valor de hoje é do Felipe."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Entendido, anotado: o valor de hoje é do Felipe.', $answer);
    }

    public function test_a_data_question_still_gets_one_nudge_then_queries_and_answers(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conversation = $this->makeConversation($user, 'Quantas tarefas eu tenho pra hoje?');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn('{"say": "Você tem 3 tarefas (chute)."}');
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Você tentou responder sem consultar o banco')))
            ->andReturn('{"query": "SELECT 1 AS ok"}');
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, '"ok":1')))
            ->andReturn('{"say": "Você tem 1 tarefa pra hoje."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Você tem 1 tarefa pra hoje.', $answer);
    }

    public function test_a_say_insisted_after_the_single_nudge_is_accepted_as_the_final_answer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conversation = $this->makeConversation(
            $user,
            'Cara, a questão do WABA o que fizemos foi a troca de API do WhatsApp. '
                .'Então o WABA já está pago. Os R$1100 de hoje foi referente ao sistema Felipe França.',
        );

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn('{"say": "Entendido: WABA pago, R$ 1.100 do Felipe França, Miro é novo escopo."}');
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Você tentou responder sem consultar o banco')))
            ->andReturn('{"say": "Entendido: WABA pago, R$ 1.100 do Felipe França, Miro é novo escopo."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Entendido: WABA pago, R$ 1.100 do Felipe França, Miro é novo escopo.', $answer);
    }

    public function test_the_last_say_wins_over_the_canned_fallback_when_the_loop_is_exhausted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conversation = $this->makeConversation($user, 'Quantas faturas vencidas eu tenho?');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn('{"say": "Resposta parcial antes de consultar."}');
        $runner->shouldReceive('complete')
            ->times(4)
            ->ordered()
            ->andReturn('{"query": "SELECT 1"}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Resposta parcial antes de consultar.', $answer);
    }

    public function test_the_canned_fallback_is_kept_when_no_say_ever_appeared(): void
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

    /**
     * The quick-chip labels, when typed by hand into the chat, must keep
     * forcing a database query before a final answer is accepted.
     */
    public function test_typed_shortcut_phrases_still_force_a_query(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['O que tenho pra hoje?', 'Faturas vencidas', 'Resumo da semana'] as $phrase) {
            $conversation = $this->makeConversation($user, $phrase);

            $runner = Mockery::mock(AiCliRunner::class);
            $runner->shouldReceive('complete')
                ->once()
                ->ordered()
                ->andReturn('{"say": "Chute sem consultar."}');
            $runner->shouldReceive('complete')
                ->once()
                ->ordered()
                ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Você tentou responder sem consultar o banco')))
                ->andReturn('{"query": "SELECT 1 AS ok"}');
            $runner->shouldReceive('complete')
                ->once()
                ->ordered()
                ->andReturn('{"say": "Resposta baseada nos dados."}');
            $this->instance(AiCliRunner::class, $runner);

            $answer = app(AssistantChatService::class)->respond($conversation);

            $this->assertSame('Resposta baseada nos dados.', $answer, "Shortcut phrase failed: {$phrase}");
        }
    }
}
