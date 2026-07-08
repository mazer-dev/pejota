<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ai\AiCliRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AiBriefingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_crosses_tasks_invoices_and_pending_conversations_into_the_prompt(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        $status = Status::create([
            'name' => 'A Fazer', 'phase' => 'todo', 'color' => '#000', 'sort_order' => 1, 'active' => true, 'company_id' => $companyId,
        ]);
        Task::create([
            'title' => 'Entregar proposta', 'status_id' => $status->id, 'company_id' => $companyId,
            'client_id' => $client->id, 'due_date' => now()->subDay()->toDateString(),
        ]);

        Invoice::create([
            'number' => 'INV-1', 'title' => 'Fatura vencida', 'client_id' => $client->id, 'company_id' => $companyId,
            'due_date' => now()->subDays(2)->toDateString(), 'total' => 500, 'status' => InvoiceStatusEnum::SENT->value,
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'evolution_instance' => 'inst',
            'remote_jid' => 'a@s.whatsapp.net', 'status' => 'open',
        ]);
        WhatsappMessage::create([
            'company_id' => $companyId, 'whatsapp_conversation_id' => $conversation->id, 'client_id' => $client->id,
            'evolution_instance' => 'inst', 'remote_message_id' => 'M1', 'remote_jid' => 'a@s.whatsapp.net',
            'from_me' => false, 'message_type' => 'chat', 'text' => 'Alguma novidade?', 'sent_at' => now(),
        ]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt): bool {
                return str_contains($prompt, 'Entregar proposta')
                    && str_contains($prompt, 'ATRASADA')
                    && str_contains($prompt, 'INV-1')
                    && str_contains($prompt, 'aguardando resposta')
                    && str_contains($prompt, '<<<DADOS>>>');
            }))
            ->andReturn('1. Responder Vivianne no WhatsApp. 2. Cobrar a fatura vencida.');
        $this->instance(AiCliRunner::class, $runner);

        $this->artisan('pj:ai-briefing', ['--company' => $companyId])
            ->assertSuccessful();
    }

    public function test_it_reports_nothing_relevant_when_there_is_no_data(): void
    {
        $user = User::factory()->create();

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldNotReceive('complete');
        $this->instance(AiCliRunner::class, $runner);

        $this->artisan('pj:ai-briefing', ['--company' => $user->company->id])
            ->expectsOutputToContain('nada relevante')
            ->assertSuccessful();
    }

    public function test_it_includes_waiting_client_tasks_with_message_recency_in_the_prompt(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        $status = Status::create([
            'name' => 'A Fazer', 'phase' => 'todo', 'color' => '#000', 'sort_order' => 1, 'active' => true, 'company_id' => $companyId,
        ]);

        $task = Task::create([
            'title' => 'Obter convite do HubSpot', 'status_id' => $status->id, 'company_id' => $companyId,
            'client_id' => $client->id,
        ]);
        $task->attachTag(Task::TAG_WAITING_CLIENT);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'evolution_instance' => 'inst',
            'remote_jid' => 'a@s.whatsapp.net', 'status' => 'open',
        ]);
        WhatsappMessage::create([
            'company_id' => $companyId, 'whatsapp_conversation_id' => $conversation->id, 'client_id' => $client->id,
            'evolution_instance' => 'inst', 'remote_message_id' => 'M1', 'remote_jid' => 'a@s.whatsapp.net',
            'from_me' => false, 'message_type' => 'chat', 'text' => 'Vou providenciar.', 'sent_at' => now()->subDays(4),
        ]);
        WhatsappMessage::create([
            'company_id' => $companyId, 'whatsapp_conversation_id' => $conversation->id, 'client_id' => $client->id,
            'evolution_instance' => 'inst', 'remote_message_id' => 'M2', 'remote_jid' => 'a@s.whatsapp.net',
            'from_me' => true, 'message_type' => 'chat', 'text' => 'Pode me mandar o convite?', 'sent_at' => now()->subDays(6),
        ]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt): bool {
                return str_contains($prompt, 'Tarefas aguardando o cliente')
                    && str_contains($prompt, 'Obter convite do HubSpot')
                    && str_contains($prompt, 'última mensagem do cliente há 4 dia(s)')
                    && str_contains($prompt, 'sua última mensagem há 6 dia(s)')
                    && str_contains($prompt, 'follow-up');
            }))
            ->andReturn('1. Fazer follow-up com a Vivianne sobre o convite do HubSpot.');
        $this->instance(AiCliRunner::class, $runner);

        $this->artisan('pj:ai-briefing', ['--company' => $companyId])
            ->assertSuccessful();
    }
}
