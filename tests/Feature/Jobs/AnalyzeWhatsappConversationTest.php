<?php

namespace Tests\Feature\Jobs;

use App\Jobs\AnalyzeWhatsappConversation;
use App\Models\Client;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\WhatsappSuggestionService;
use App\Services\Evolution\EvolutionWebhookHandler;
use App\Services\Evolution\WhatsappConversationTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Bus;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AnalyzeWhatsappConversationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WhatsappConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $client = Client::create([
            'company_id' => $this->user->company->id,
            'name' => 'Vivianne',
        ]);

        $this->conversation = WhatsappConversation::create([
            'company_id' => $this->user->company->id,
            'client_id' => $client->id,
            'evolution_instance' => 'inst',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'push_name' => 'Vivianne',
            'status' => 'open',
        ]);
    }

    private function makeMessage(string $text, bool $fromMe = false): WhatsappMessage
    {
        return WhatsappMessage::create([
            'company_id' => $this->user->company->id,
            'whatsapp_conversation_id' => $this->conversation->id,
            'client_id' => $this->conversation->client_id,
            'evolution_instance' => 'inst',
            'remote_message_id' => 'MSG-'.fake()->unique()->uuid(),
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'sender_name' => $fromMe ? null : 'Vivianne',
            'from_me' => $fromMe,
            'message_type' => 'text',
            'text' => $text,
            'sent_at' => now(),
        ]);
    }

    private function runJob(WhatsappMessage $message): void
    {
        (new AnalyzeWhatsappConversation($this->conversation, $message))
            ->handle(app(WhatsappSuggestionService::class));
    }

    public function test_it_creates_suggestions_and_sends_one_consolidated_notification(): void
    {
        $message = $this->makeMessage('Segue a senha do painel: hunter2. E preciso de um relatório novo.');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn(json_encode([
            ['type' => 'note', 'title' => 'Credencial do painel', 'content' => 'Senha: hunter2.'],
            ['type' => 'task', 'title' => 'Criar relatório novo', 'content' => 'Cliente pediu relatório.'],
        ]));
        $this->instance(AiCliRunner::class, $runner);

        $this->runJob($message);

        $this->assertDatabaseCount('whatsapp_suggestions', 2);
        $this->assertSame($message->id, $this->conversation->fresh()->last_suggested_message_id);

        $this->assertSame(1, DatabaseNotification::where('notifiable_id', $this->user->id)->count());

        $notification = DatabaseNotification::where('notifiable_id', $this->user->id)->first();
        $this->assertStringContainsString('2 sugestões da IA', $notification->data['title']);
        $this->assertStringContainsString('Vivianne', $notification->data['title']);
    }

    public function test_it_is_a_no_op_when_the_flag_is_off(): void
    {
        config(['services.ai_whatsapp_suggestions' => false]);

        $message = $this->makeMessage('Preciso de um site novo.');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldNotReceive('complete');
        $this->instance(AiCliRunner::class, $runner);

        $this->runJob($message);

        $this->assertDatabaseCount('whatsapp_suggestions', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertNull($this->conversation->fresh()->last_suggested_message_id);
    }

    public function test_it_aborts_when_a_newer_inbound_message_arrived(): void
    {
        $older = $this->makeMessage('Primeira mensagem da rajada.');
        $this->makeMessage('Segunda mensagem da rajada.');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldNotReceive('complete');
        $this->instance(AiCliRunner::class, $runner);

        $this->runJob($older);

        $this->assertDatabaseCount('whatsapp_suggestions', 0);
        $this->assertNull($this->conversation->fresh()->last_suggested_message_id);
    }

    public function test_it_aborts_when_the_message_was_already_analyzed(): void
    {
        $message = $this->makeMessage('Mensagem já coberta por outra análise.');

        $this->conversation->forceFill(['last_suggested_message_id' => $message->id])->save();

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldNotReceive('complete');
        $this->instance(AiCliRunner::class, $runner);

        $this->runJob($message);

        $this->assertDatabaseCount('whatsapp_suggestions', 0);
    }

    public function test_it_sends_only_messages_newer_than_the_last_analysis_as_new(): void
    {
        $old = $this->makeMessage('Mensagem antiga já analisada.');
        $this->conversation->forceFill(['last_suggested_message_id' => $old->id])->save();

        $new = $this->makeMessage('Mensagem nova para analisar.');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt): bool {
                $newSection = (string) str($prompt)->after('Mensagens novas desde a última análise');

                return str_contains($newSection, 'Mensagem nova para analisar.')
                    && ! str_contains($newSection, 'Mensagem antiga já analisada.');
            }))
            ->andReturn('[]');
        $this->instance(AiCliRunner::class, $runner);

        $this->runJob($new);

        $this->assertSame($new->id, $this->conversation->fresh()->last_suggested_message_id);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_it_notifies_failure_and_keeps_the_analysis_anchor_when_the_ai_cli_throws(): void
    {
        $message = $this->makeMessage('Mensagem que falha.');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andThrow(new RuntimeException('CLI indisponível.'));
        $this->instance(AiCliRunner::class, $runner);

        $this->runJob($message);

        $this->assertDatabaseCount('whatsapp_suggestions', 0);
        $this->assertNull($this->conversation->fresh()->last_suggested_message_id);

        $notification = DatabaseNotification::where('notifiable_id', $this->user->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Falha ao gerar sugestões', $notification->data['title']);
    }

    public function test_webhook_ingestion_dispatches_the_job_with_a_delay_for_inbound_messages(): void
    {
        Bus::fake([AnalyzeWhatsappConversation::class]);

        config([
            'services.evolution.default_company_id' => $this->user->company->id,
            'services.evolution.instance' => 'inst',
        ]);

        $this->instance(
            WhatsappConversationTokenService::class,
            Mockery::mock(WhatsappConversationTokenService::class)->shouldIgnoreMissing(),
        );

        app(EvolutionWebhookHandler::class)->handle($this->webhookPayload(fromMe: false));

        Bus::assertDispatched(AnalyzeWhatsappConversation::class, function (AnalyzeWhatsappConversation $job): bool {
            return $job->delay !== null;
        });
    }

    public function test_webhook_ingestion_does_not_dispatch_for_outgoing_messages_or_when_disabled(): void
    {
        Bus::fake([AnalyzeWhatsappConversation::class]);

        config([
            'services.evolution.default_company_id' => $this->user->company->id,
            'services.evolution.instance' => 'inst',
        ]);

        $this->instance(
            WhatsappConversationTokenService::class,
            Mockery::mock(WhatsappConversationTokenService::class)->shouldIgnoreMissing(),
        );

        app(EvolutionWebhookHandler::class)->handle($this->webhookPayload(fromMe: true));

        config(['services.ai_whatsapp_suggestions' => false]);

        app(EvolutionWebhookHandler::class)->handle($this->webhookPayload(fromMe: false));

        Bus::assertNotDispatched(AnalyzeWhatsappConversation::class);
    }

    public function test_bulk_imports_do_not_dispatch_the_job(): void
    {
        Bus::fake([AnalyzeWhatsappConversation::class]);

        config([
            'services.evolution.default_company_id' => $this->user->company->id,
            'services.evolution.instance' => 'inst',
        ]);

        $this->instance(
            WhatsappConversationTokenService::class,
            Mockery::mock(WhatsappConversationTokenService::class)->shouldIgnoreMissing(),
        );

        app(EvolutionWebhookHandler::class)
            ->handle($this->webhookPayload(fromMe: false), dispatchSuggestions: false);

        Bus::assertNotDispatched(AnalyzeWhatsappConversation::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookPayload(bool $fromMe): array
    {
        return [
            'event' => 'messages.upsert',
            'instance' => 'inst',
            'sender' => '5511999990000@s.whatsapp.net',
            'date_time' => now()->toISOString(),
            'data' => [
                'key' => [
                    'remoteJid' => '5511999990000@s.whatsapp.net',
                    'id' => 'HOOK-'.fake()->unique()->uuid(),
                    'fromMe' => $fromMe,
                ],
                'pushName' => 'Vivianne',
                'messageType' => 'conversation',
                'messageTimestamp' => now()->timestamp,
                'message' => [
                    'conversation' => 'Oi, preciso de uma alteração no site.',
                ],
            ],
        ];
    }
}
