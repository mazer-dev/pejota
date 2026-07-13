<?php

namespace Tests\Feature\Assistant;

use App\Jobs\AnalyzeWhatsappConversation;
use App\Jobs\ProcessAssistantWhatsappMessage;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantMessageAttachment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Services\Ai\AssistantInvoiceService;
use App\Services\Evolution\AssistantWhatsappWebhookHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssistantWhatsappWebhookHandlerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->companyId = $this->user->company->id;

        config([
            'services.assistant.whatsapp.enabled' => true,
            'services.assistant.whatsapp.instance' => 'Assistente_Pejota',
            'services.assistant.whatsapp.allowed_numbers' => ['5554999371490', '5581985573942'],
            'services.assistant.whatsapp.end_command' => '#fim',
            'services.assistant.whatsapp.help_command' => '#ajuda',
            'services.assistant.whatsapp.ack_enabled' => true,
            'services.assistant.whatsapp.debounce_seconds' => 15,
            'services.evolution.default_company_id' => $this->companyId,
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'geolead_funnel_2',
        ]);

        Http::fake([
            'http://evolution.test/*' => Http::response(['key' => ['id' => 'SENT1']]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $text = 'Quantas tarefas abertas eu tenho?', array $overrides = []): array
    {
        $base = [
            'event' => 'messages.upsert',
            'instance' => 'Assistente_Pejota',
            'sender' => '5554999371490@s.whatsapp.net',
            'date_time' => now()->toISOString(),
            'data' => [
                'key' => [
                    'remoteJid' => '5554999371490@s.whatsapp.net',
                    'id' => 'MSG'.fake()->unique()->numerify('####'),
                    'fromMe' => false,
                ],
                'pushName' => 'Luiz',
                'messageType' => 'conversation',
                'messageTimestamp' => now()->timestamp,
                'message' => [
                    'conversation' => $text,
                ],
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }

    private function handler(): AssistantWhatsappWebhookHandler
    {
        return app(AssistantWhatsappWebhookHandler::class);
    }

    public function test_an_allowlisted_message_creates_a_session_a_message_a_job_and_an_ack_without_touching_client_tables(): void
    {
        Bus::fake();

        $handled = $this->handler()->handle($this->payload());

        $this->assertSame(1, $handled);

        $conversation = AssistantConversation::allTenants()->sole();
        $this->assertSame(AssistantConversation::CHANNEL_WHATSAPP, $conversation->channel);
        $this->assertSame('5554999371490', $conversation->whatsapp_number);
        $this->assertSame($this->user->id, $conversation->user_id);
        $this->assertNull($conversation->closed_at);
        $this->assertSame('Quantas tarefas abertas eu tenho?', $conversation->title);

        $this->assertDatabaseHas('assistant_messages', [
            'assistant_conversation_id' => $conversation->id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => 'Quantas tarefas abertas eu tenho?',
        ]);

        $this->assertSame(0, WhatsappConversation::allTenants()->count());
        $this->assertDatabaseCount('whatsapp_messages', 0);

        Bus::assertDispatched(
            ProcessAssistantWhatsappMessage::class,
            fn (ProcessAssistantWhatsappMessage $job): bool => $job->conversation->is($conversation)
                && $job->user->is($this->user)
                && $job->pendingAudioPath === null,
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), '/message/sendText/Assistente_Pejota')
            && $request['text'] === AssistantWhatsappWebhookHandler::ACK_TEXT);
    }

    public function test_a_non_allowlisted_number_is_silently_ignored(): void
    {
        Bus::fake();

        $handled = $this->handler()->handle($this->payload('Oi', [
            'sender' => '5511999990000@s.whatsapp.net',
            'data' => ['key' => ['remoteJid' => '5511999990000@s.whatsapp.net']],
        ]));

        $this->assertSame(0, $handled);
        $this->assertDatabaseCount('assistant_conversations', 0);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_a_jid_without_the_ninth_digit_is_accepted(): void
    {
        Bus::fake();

        $handled = $this->handler()->handle($this->payload('Oi', [
            'sender' => '555499371490@s.whatsapp.net',
            'data' => ['key' => ['remoteJid' => '555499371490@s.whatsapp.net']],
        ]));

        $this->assertSame(1, $handled);
        $this->assertSame('555499371490', AssistantConversation::allTenants()->sole()->whatsapp_number);
    }

    public function test_from_me_and_send_message_events_are_ignored_to_avoid_loops(): void
    {
        Bus::fake();

        $fromMe = $this->handler()->handle($this->payload('Eco da própria resposta', [
            'data' => ['key' => ['fromMe' => true]],
        ]));

        $sendEvent = $this->handler()->handle($this->payload('Eco', [
            'event' => 'send.message',
        ]));

        $this->assertSame(0, $fromMe);
        $this->assertSame(0, $sendEvent);
        $this->assertDatabaseCount('assistant_conversations', 0);
        Bus::assertNothingDispatched();
    }

    public function test_messages_reuse_the_open_session_and_a_new_one_opens_after_the_end_command(): void
    {
        Bus::fake();

        $this->handler()->handle($this->payload('Primeira pergunta'));
        $first = AssistantConversation::allTenants()->sole();

        // Simulate the assistant having answered, so the next message still
        // lands in the same open session.
        $first->messages()->create([
            'company_id' => $this->companyId,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => 'Resposta.',
        ]);

        $this->handler()->handle($this->payload('Segunda pergunta'));
        $this->assertSame(1, AssistantConversation::allTenants()->count());
        $this->assertSame(3, $first->refresh()->messages()->count());

        $this->handler()->handle($this->payload('#fim'));
        $this->assertNotNull($first->refresh()->closed_at);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/message/sendText/')
            && $request['text'] === AssistantWhatsappWebhookHandler::SESSION_CLOSED_TEXT);

        $this->handler()->handle($this->payload('Terceira pergunta, novo atendimento'));

        $this->assertSame(2, AssistantConversation::allTenants()->count());
        $second = AssistantConversation::allTenants()->orderByDesc('id')->first();
        $this->assertNull($second->closed_at);
        $this->assertSame('Terceira pergunta, novo atendimento', $second->title);
    }

    public function test_end_command_without_an_active_session_answers_the_no_session_text(): void
    {
        Bus::fake();

        $handled = $this->handler()->handle($this->payload('#fim'));

        $this->assertSame(1, $handled);
        $this->assertDatabaseCount('assistant_conversations', 0);
        Bus::assertNothingDispatched();

        Http::assertSent(fn ($request) => $request['text'] === AssistantWhatsappWebhookHandler::NO_SESSION_TEXT);
    }

    public function test_help_command_answers_the_static_help_text_without_creating_anything(): void
    {
        Bus::fake();

        $handled = $this->handler()->handle($this->payload('#ajuda'));

        $this->assertSame(1, $handled);
        $this->assertDatabaseCount('assistant_conversations', 0);
        Bus::assertNothingDispatched();

        Http::assertSent(fn ($request) => is_string($request['text'] ?? null)
            && str_contains($request['text'], 'Assistente de Dados do PeJota')
            && str_contains($request['text'], '#fim'));
    }

    public function test_a_burst_of_messages_sends_the_ack_only_once(): void
    {
        Bus::fake();

        $this->handler()->handle($this->payload('Primeira da rajada'));
        $this->handler()->handle($this->payload('Segunda da rajada'));

        $ackCount = 0;
        Http::assertSent(function ($request) use (&$ackCount) {
            if (($request['text'] ?? null) === AssistantWhatsappWebhookHandler::ACK_TEXT) {
                $ackCount++;
            }

            return true;
        });

        $this->assertSame(1, $ackCount);

        Bus::assertDispatchedTimes(ProcessAssistantWhatsappMessage::class, 2);
    }

    public function test_an_image_with_caption_becomes_an_assistant_message_attachment(): void
    {
        Bus::fake();

        $imageFile = UploadedFile::fake()->image('foto.jpg', 20, 20);
        $imageBytes = file_get_contents($imageFile->getRealPath());

        $handled = $this->handler()->handle($this->payload('', [
            'data' => [
                'messageType' => 'imageMessage',
                'message' => [
                    'conversation' => null,
                    'imageMessage' => [
                        'caption' => 'O que aparece nessa foto?',
                        'mimetype' => 'image/jpeg',
                    ],
                    'base64' => base64_encode($imageBytes),
                ],
            ],
        ]));

        $this->assertSame(1, $handled);

        $message = AssistantMessage::allTenants()->where('role', AssistantMessage::ROLE_USER)->sole();
        $this->assertSame('O que aparece nessa foto?', $message->content);

        $attachment = AssistantMessageAttachment::allTenants()->sole();
        $this->assertSame($message->id, $attachment->assistant_message_id);
        $this->assertSame('image/jpeg', $attachment->mime_type);
        $this->assertSame(AssistantMessageAttachment::STATUS_STORED, $attachment->status);
        Storage::disk('local')->assertExists($attachment->path);

        Bus::assertDispatched(ProcessAssistantWhatsappMessage::class);
    }

    public function test_a_video_is_answered_with_the_unsupported_media_text(): void
    {
        Bus::fake();

        $handled = $this->handler()->handle($this->payload('', [
            'data' => [
                'messageType' => 'videoMessage',
                'message' => [
                    'conversation' => null,
                    'videoMessage' => ['mimetype' => 'video/mp4'],
                ],
            ],
        ]));

        $this->assertSame(1, $handled);
        $this->assertDatabaseCount('assistant_conversations', 0);
        Bus::assertNothingDispatched();

        Http::assertSent(fn ($request) => $request['text'] === AssistantWhatsappWebhookHandler::UNSUPPORTED_MEDIA_TEXT);
    }

    public function test_an_audio_message_creates_a_placeholder_and_dispatches_the_job_with_the_audio_path(): void
    {
        Bus::fake();

        $handled = $this->handler()->handle($this->payload('', [
            'data' => [
                'messageType' => 'audioMessage',
                'message' => [
                    'conversation' => null,
                    'audioMessage' => ['mimetype' => 'audio/ogg; codecs=opus'],
                    'base64' => base64_encode('fake-ogg-bytes'),
                ],
            ],
        ]));

        $this->assertSame(1, $handled);

        $message = AssistantMessage::allTenants()->where('role', AssistantMessage::ROLE_USER)->sole();
        $this->assertSame(AssistantWhatsappWebhookHandler::AUDIO_PLACEHOLDER, $message->content);

        $this->assertDatabaseCount('assistant_message_attachments', 0);

        Bus::assertDispatched(
            ProcessAssistantWhatsappMessage::class,
            function (ProcessAssistantWhatsappMessage $job): bool {
                return $job->pendingAudioPath !== null
                    && str_contains($job->pendingAudioPath, '/tmp/')
                    && Storage::disk('local')->exists($job->pendingAudioPath);
            },
        );
    }

    public function test_the_correct_passphrase_creates_the_invoice_synchronously_without_dispatching_a_job(): void
    {
        Bus::fake();

        $this->actingAs($this->user);

        $client = Client::create(['company_id' => $this->companyId, 'name' => 'Felipe França']);
        $unit = Unit::create(['name' => 'Hora', 'symbol' => 'h', 'company_id' => $this->companyId]);
        $product = Product::create([
            'name' => 'Desenvolvimento',
            'service' => true,
            'digital' => false,
            'price' => 90.00,
            'unit_id' => $unit->id,
            'company_id' => $this->companyId,
        ]);

        $session = AssistantConversation::create([
            'company_id' => $this->companyId,
            'user_id' => $this->user->id,
            'title' => 'Fatura',
            'channel' => AssistantConversation::CHANNEL_WHATSAPP,
            'whatsapp_number' => '5554999371490',
        ]);

        [$draft, $errors] = app(AssistantInvoiceService::class)->validateDraft([
            'client_id' => $client->id,
            'title' => 'Desenvolvimento julho',
            'due_date' => now()->addDays(7)->toDateString(),
            'items' => [
                [
                    'name' => 'Horas de desenvolvimento',
                    'quantity' => 20,
                    'price_cents' => 9000,
                    'product_id' => $product->id,
                    'unit_id' => $unit->id,
                ],
            ],
        ], $this->companyId);
        $this->assertSame([], $errors);

        $session->forceFill([
            'pending_action' => [
                'type' => 'create_invoice',
                'draft' => $draft,
                'passphrase' => 'Girassol',
                'expires_at' => now()->addMinutes(15)->toISOString(),
            ],
        ])->save();

        auth()->logout();

        $handled = $this->handler()->handle($this->payload('Girassol'));

        $this->assertSame(1, $handled);
        $this->assertSame(1, Invoice::allTenants()->count());
        $this->assertNull($session->refresh()->pending_action);

        Bus::assertNothingDispatched();

        Http::assertSent(fn ($request) => is_string($request['text'] ?? null)
            && str_contains($request['text'], 'Fatura'));
    }

    public function test_the_client_facing_instance_keeps_the_original_flow_even_with_the_assistant_enabled(): void
    {
        Bus::fake();

        WhatsappConversation::create([
            'company_id' => $this->companyId,
            'name' => 'Cliente autorizado',
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'status' => 'open',
        ]);

        config([
            'services.evolution.webhook_token' => null,
            'services.evolution.webhook_verify_api_key' => false,
            'services.evolution.webhook_forward_url' => null,
        ]);

        $response = $this->postJson('/webhooks/evolution', [
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '5511999990000@s.whatsapp.net',
            'date_time' => now()->toISOString(),
            'data' => [
                'key' => [
                    'remoteJid' => '5511999990000@s.whatsapp.net',
                    'id' => 'CLIENT1',
                    'fromMe' => false,
                ],
                'pushName' => 'Cliente',
                'messageType' => 'conversation',
                'messageTimestamp' => now()->timestamp,
                'message' => ['conversation' => 'Oi, quero um orçamento'],
            ],
        ]);

        $response->assertOk();

        $this->assertSame(1, WhatsappConversation::allTenants()->count());
        $this->assertDatabaseCount('assistant_conversations', 0);
        Bus::assertNotDispatched(AnalyzeWhatsappConversation::class);
        Bus::assertNotDispatched(ProcessAssistantWhatsappMessage::class);
    }

    public function test_the_assistant_instance_routes_through_the_webhook_endpoint(): void
    {
        Bus::fake();

        config([
            'services.evolution.webhook_token' => null,
            'services.evolution.webhook_verify_api_key' => false,
        ]);

        $response = $this->postJson('/webhooks/evolution', $this->payload('Pergunta pelo endpoint'));

        $response->assertOk()->assertJson(['ok' => true, 'handled' => 1]);

        $this->assertSame(1, AssistantConversation::allTenants()->count());
        $this->assertSame(0, WhatsappConversation::allTenants()->count());
        Bus::assertDispatched(ProcessAssistantWhatsappMessage::class);
    }
}
