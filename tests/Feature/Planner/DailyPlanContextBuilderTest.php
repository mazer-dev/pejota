<?php

namespace Tests\Feature\Planner;

use App\Enums\InvoiceStatusEnum;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Planner\DailyPlanContext;
use App\Services\Planner\DailyPlanContextBuilder;
use App\Services\Planner\PlannerCapacity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyPlanContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Status $status;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->client = Client::create([
            'company_id' => $this->user->company->id,
            'name' => 'Vivianne',
        ]);

        $this->status = Status::create([
            'name' => 'A Fazer',
            'phase' => 'todo',
            'color' => '#000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->user->company->id,
        ]);
    }

    private function build(): DailyPlanContext
    {
        $company = $this->user->company;

        return app(DailyPlanContextBuilder::class)->build(
            $company,
            CarbonImmutable::now()->startOfDay(),
            PlannerCapacity::forCompany($company),
        );
    }

    private function makeConversation(): WhatsappConversation
    {
        return WhatsappConversation::create([
            'company_id' => $this->user->company->id,
            'client_id' => $this->client->id,
            'evolution_instance' => 'inst',
            'remote_jid' => 'a@s.whatsapp.net',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
    }

    private function makeMessage(WhatsappConversation $conversation, bool $fromMe, string $text, \DateTimeInterface $sentAt): WhatsappMessage
    {
        return WhatsappMessage::create([
            'company_id' => $this->user->company->id,
            'whatsapp_conversation_id' => $conversation->id,
            'client_id' => $this->client->id,
            'evolution_instance' => 'inst',
            'remote_message_id' => 'M'.uniqid(),
            'remote_jid' => 'a@s.whatsapp.net',
            'from_me' => $fromMe,
            'message_type' => 'chat',
            'text' => $text,
            'sent_at' => $sentAt,
        ]);
    }

    public function test_task_is_flagged_possibly_blocked_when_our_last_message_is_unanswered_for_days(): void
    {
        $task = Task::create([
            'title' => 'Configurar e-mails da Vivianne',
            'status_id' => $this->status->id,
            'company_id' => $this->user->company->id,
            'client_id' => $this->client->id,
        ]);

        $conversation = $this->makeConversation();
        $this->makeMessage($conversation, fromMe: false, text: 'Vou verificar.', sentAt: now()->subDays(3));
        $this->makeMessage($conversation, fromMe: true, text: 'Conseguiu o acesso?', sentAt: now()->subDays(2));

        $context = $this->build();

        $this->assertStringContainsString('POSSIVELMENTE BLOQUEADA', $context->text);
        $this->assertStringContainsString('Configurar e-mails da Vivianne', $context->text);
        $this->assertContains($task->id, $context->validTaskIds);
        $this->assertContains($conversation->id, $context->validConversationIds);
    }

    public function test_task_with_waiting_client_tag_is_flagged_blocked(): void
    {
        $task = Task::create([
            'title' => 'Obter convite do HubSpot',
            'status_id' => $this->status->id,
            'company_id' => $this->user->company->id,
            'client_id' => $this->client->id,
        ]);
        $task->attachTag(Task::TAG_WAITING_CLIENT);

        $context = $this->build();

        $this->assertStringContainsString('BLOQUEADA: marcada como aguardando o cliente', $context->text);
    }

    public function test_task_is_flagged_when_client_is_waiting_for_our_reply(): void
    {
        Task::create([
            'title' => 'Ajustar layout',
            'status_id' => $this->status->id,
            'company_id' => $this->user->company->id,
            'client_id' => $this->client->id,
        ]);

        $conversation = $this->makeConversation();
        $this->makeMessage($conversation, fromMe: false, text: 'Alguma novidade?', sentAt: now()->subHours(5));

        $context = $this->build();

        $this->assertStringContainsString('CLIENTE AGUARDANDO SUA RESPOSTA', $context->text);
    }

    public function test_effort_is_normalized_to_minutes_and_habits_are_listed_separately(): void
    {
        Task::create([
            'title' => 'Tarefa com estimativa',
            'status_id' => $this->status->id,
            'company_id' => $this->user->company->id,
            'effort' => 2,
            'effort_unit' => 'h',
        ]);

        Task::create([
            'title' => 'Beber água',
            'status_id' => $this->status->id,
            'company_id' => $this->user->company->id,
            'is_continuous' => true,
        ]);

        $context = $this->build();

        $this->assertStringContainsString('estimativa 02h00', $context->text);
        $this->assertStringContainsString('Hábitos diários', $context->text);
        $this->assertStringContainsString('Beber água', $context->text);
    }

    public function test_invoices_needing_attention_are_included_with_ids(): void
    {
        $invoice = Invoice::create([
            'number' => 'INV-9',
            'title' => 'Fatura vencida',
            'client_id' => $this->client->id,
            'company_id' => $this->user->company->id,
            'due_date' => now()->subDays(2)->toDateString(),
            'total' => 500,
            'status' => InvoiceStatusEnum::SENT->value,
        ]);

        $context = $this->build();

        $this->assertStringContainsString('VENCIDA', $context->text);
        $this->assertStringContainsString('[fatura #'.$invoice->id.']', $context->text);
        $this->assertContains($invoice->id, $context->validInvoiceIds);
    }

    public function test_conversation_summaries_show_direction_and_truncated_text(): void
    {
        $conversation = $this->makeConversation();
        $this->makeMessage($conversation, fromMe: true, text: str_repeat('a', 500), sentAt: now()->subHour());
        $this->makeMessage($conversation, fromMe: false, text: 'Ok, combinado!', sentAt: now());

        $context = $this->build();

        $this->assertStringContainsString('Conversas recentes de WhatsApp', $context->text);
        $this->assertStringContainsString('Luiz (', $context->text);
        $this->assertStringContainsString('Cliente (', $context->text);
        $this->assertStringNotContainsString(str_repeat('a', 500), $context->text);
    }

    public function test_context_degrades_when_over_the_char_budget(): void
    {
        config(['services.planner.context_max_chars' => 1500]);

        $conversation = $this->makeConversation();
        for ($i = 0; $i < 10; $i++) {
            $this->makeMessage($conversation, fromMe: $i % 2 === 0, text: str_repeat('mensagem longa ', 15).$i, sentAt: now()->subMinutes(60 - $i));
        }

        $context = $this->build();

        $this->assertTrue($context->truncated);
        $this->assertStringContainsString('[contexto truncado', $context->text);
    }

    public function test_freshness_section_warns_when_no_whatsapp_messages_exist(): void
    {
        $context = $this->build();

        $this->assertStringContainsString('Nenhuma mensagem de WhatsApp sincronizada', $context->text);
    }
}
