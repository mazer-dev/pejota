<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientAiAnalysis;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\CliWhatsappMessageSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CliWhatsappMessageSuggesterEnrichedContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_tasks_invoices_and_saved_analysis_to_the_ai_cli(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;
        $this->actingAs($user);

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        $status = Status::create([
            'name' => 'A Fazer', 'phase' => 'todo', 'color' => '#000', 'sort_order' => 1, 'active' => true, 'company_id' => $companyId,
        ]);
        Task::create([
            'title' => 'Confirmar escopo do módulo financeiro', 'status_id' => $status->id, 'company_id' => $companyId,
            'client_id' => $client->id, 'due_date' => now()->subDays(2)->toDateString(),
        ]);

        ClientAiAnalysis::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'content' => 'Temperatura: cliente satisfeito.',
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'evolution_instance' => 'inst',
            'remote_jid' => 'a@s.whatsapp.net', 'status' => 'open',
        ]);

        WhatsappMessage::create([
            'company_id' => $companyId, 'whatsapp_conversation_id' => $conversation->id, 'client_id' => $client->id,
            'evolution_instance' => 'inst', 'remote_message_id' => 'M1', 'remote_jid' => 'a@s.whatsapp.net',
            'from_me' => false, 'message_type' => 'chat', 'text' => 'Como está o andamento?', 'sent_at' => now(),
        ]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt): bool {
                return str_contains($prompt, 'Confirmar escopo do módulo financeiro')
                    && str_contains($prompt, 'ATRASADA')
                    && str_contains($prompt, 'Temperatura: cliente satisfeito.')
                    && str_contains($prompt, 'próximos passos concretos')
                    && str_contains($prompt, '<<<DADOS>>>');
            }))
            ->andReturn('Oi! Ainda estamos com um item atrasado, vou priorizar hoje.');

        $this->instance(AiCliRunner::class, $runner);

        $suggestion = app(CliWhatsappMessageSuggester::class)->suggest($conversation);

        $this->assertSame('Oi! Ainda estamos com um item atrasado, vou priorizar hoje.', $suggestion);
    }

    public function test_an_instruction_changes_the_goal_of_the_prompt(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;
        $this->actingAs($user);

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'evolution_instance' => 'inst',
            'remote_jid' => 'a@s.whatsapp.net', 'status' => 'open',
        ]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt): bool {
                return str_contains($prompt, 'avisar que a entrega atrasa 2 dias')
                    && str_contains($prompt, 'cumprindo essa instrução')
                    && ! str_contains($prompt, 'Escreva a próxima mensagem que o Luiz deve enviar no WhatsApp.');
            }))
            ->andReturn('Oi Vivianne! A entrega vai precisar de mais 2 dias.');

        $this->instance(AiCliRunner::class, $runner);

        $suggestion = app(CliWhatsappMessageSuggester::class)
            ->suggest($conversation, null, 'avisar que a entrega atrasa 2 dias');

        $this->assertSame('Oi Vivianne! A entrega vai precisar de mais 2 dias.', $suggestion);
    }

    public function test_without_instruction_the_prompt_keeps_the_default_goal(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;
        $this->actingAs($user);

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'evolution_instance' => 'inst',
            'remote_jid' => 'a@s.whatsapp.net', 'status' => 'open',
        ]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Escreva a próxima mensagem que o Luiz deve enviar no WhatsApp.')
                && ! str_contains($prompt, 'cumprindo essa instrução')))
            ->andReturn('Tudo certo por aqui!');

        $this->instance(AiCliRunner::class, $runner);

        $suggestion = app(CliWhatsappMessageSuggester::class)->suggest($conversation);

        $this->assertSame('Tudo certo por aqui!', $suggestion);
    }
}
