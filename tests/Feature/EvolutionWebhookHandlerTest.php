<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeWhatsappConversation;
use App\Models\Client;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Evolution\EvolutionWebhookHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvolutionWebhookHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_messages_only_in_an_existing_conversation_and_preserves_the_manual_name(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.default_company_id' => $companyId,
            'services.evolution.instance' => 'geolead_funnel_2',
        ]);

        $client = Client::create([
            'company_id' => $companyId,
            'name' => 'Cliente WhatsApp',
            'phone' => '(11) 99999-0000',
            'ai_context' => 'Cliente veio da 99freelas.',
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'client_id' => $client->id,
            'name' => 'João — Financeiro',
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'push_name' => 'Nome remoto antigo',
            'status' => 'open',
        ]);

        Bus::fake([AnalyzeWhatsappConversation::class]);

        app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '5511999990000@s.whatsapp.net',
            'date_time' => now()->toISOString(),
            'data' => [
                'key' => [
                    'remoteJid' => '5511999990000@s.whatsapp.net',
                    'id' => 'ABC123',
                    'fromMe' => false,
                ],
                'pushName' => 'Cliente WhatsApp',
                'messageType' => 'conversation',
                'messageTimestamp' => now()->timestamp,
                'message' => [
                    'conversation' => 'Oi, tudo bem?',
                ],
            ],
        ]);

        $conversation->refresh();
        $this->assertSame($client->id, $conversation->client_id);
        $this->assertSame('João — Financeiro', $conversation->name);
        $this->assertSame('Cliente WhatsApp', $conversation->push_name);
        $this->assertSame('5511999990000', $conversation->phone_number);
        $this->assertGreaterThan(0, $conversation->context_tokens);
        $this->assertDatabaseHas('whatsapp_messages', [
            'remote_message_id' => 'ABC123',
            'text' => 'Oi, tudo bem?',
        ]);
        Bus::assertNotDispatched(AnalyzeWhatsappConversation::class);
    }

    public function test_an_unknown_number_is_ignored_before_records_files_tokens_or_jobs(): void
    {
        $user = User::factory()->create();
        config(['services.evolution.default_company_id' => $user->company->id]);
        Storage::fake('local');
        Bus::fake();

        $handled = app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '5511888887777@s.whatsapp.net',
            'data' => [
                'key' => ['remoteJid' => '5511888887777@s.whatsapp.net', 'id' => 'UNKNOWN1', 'fromMe' => false],
                'messageType' => 'imageMessage',
                'messageTimestamp' => now()->timestamp,
                'message' => ['imageMessage' => ['mimetype' => 'image/png', 'base64' => base64_encode('image')]],
            ],
        ]);

        $this->assertSame(0, $handled);
        $this->assertDatabaseCount('whatsapp_conversations', 0);
        $this->assertDatabaseCount('whatsapp_messages', 0);
        $this->assertDatabaseCount('whatsapp_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        Bus::assertNothingDispatched();
    }

    public function test_from_me_messages_do_not_rename_the_conversation(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.default_company_id' => $companyId,
            'services.evolution.instance' => 'geolead_funnel_2',
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'name' => 'Nome manual soberano',
            'push_name' => 'Vivianne',
            'status' => 'open',
        ]);

        app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '5511999990000@s.whatsapp.net',
            'date_time' => now()->toISOString(),
            'data' => [
                'key' => [
                    'remoteJid' => '5511999990000@s.whatsapp.net',
                    'id' => 'MINE1',
                    'fromMe' => true,
                ],
                'pushName' => 'Luiz Fernando',
                'messageType' => 'conversation',
                'messageTimestamp' => now()->timestamp,
                'message' => [
                    'conversation' => 'Bom dia!',
                ],
            ],
        ]);

        $this->assertSame('Vivianne', $conversation->refresh()->push_name);
        $this->assertSame('Nome manual soberano', $conversation->name);
        $this->assertDatabaseHas('whatsapp_messages', [
            'remote_message_id' => 'MINE1',
            'sender_name' => 'Luiz Fernando',
        ]);
    }

    public function test_each_message_stores_only_its_own_record_in_the_payload(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.default_company_id' => $companyId,
            'services.evolution.instance' => 'geolead_funnel_2',
        ]);

        WhatsappConversation::create([
            'company_id' => $companyId,
            'name' => 'Cliente autorizado',
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'status' => 'open',
        ]);

        app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '5511999990000@s.whatsapp.net',
            'date_time' => now()->toISOString(),
            'data' => [
                [
                    'key' => [
                        'remoteJid' => '5511999990000@s.whatsapp.net',
                        'id' => 'BATCH1',
                        'fromMe' => false,
                    ],
                    'pushName' => 'Cliente',
                    'messageType' => 'conversation',
                    'messageTimestamp' => now()->timestamp,
                    'message' => ['conversation' => 'Primeira'],
                ],
                [
                    'key' => [
                        'remoteJid' => '5511999990000@s.whatsapp.net',
                        'id' => 'BATCH2',
                        'fromMe' => false,
                    ],
                    'pushName' => 'Cliente',
                    'messageType' => 'conversation',
                    'messageTimestamp' => now()->timestamp,
                    'message' => ['conversation' => 'Segunda'],
                ],
            ],
        ], dispatchSuggestions: false);

        $first = WhatsappMessage::allTenants()->where('remote_message_id', 'BATCH1')->firstOrFail();
        $second = WhatsappMessage::allTenants()->where('remote_message_id', 'BATCH2')->firstOrFail();

        $this->assertSame('BATCH1', data_get($first->payload, 'data.key.id'));
        $this->assertSame('BATCH2', data_get($second->payload, 'data.key.id'));
        $this->assertFalse(array_is_list($first->payload['data']));
    }

    public function test_it_preserves_existing_text_when_resync_payload_has_no_text(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.default_company_id' => $companyId,
            'services.evolution.instance' => 'geolead_funnel_2',
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'push_name' => 'Cliente',
            'status' => 'open',
        ]);

        $message = WhatsappMessage::create([
            'company_id' => $companyId,
            'whatsapp_conversation_id' => $conversation->id,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_message_id' => 'AUDIO1',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'from_me' => false,
            'message_type' => 'audio',
            'text' => 'Transcrição existente do áudio.',
            'sent_at' => now(),
        ]);

        app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '5511999990000@s.whatsapp.net',
            'date_time' => now()->toISOString(),
            'data' => [
                'key' => [
                    'remoteJid' => '5511999990000@s.whatsapp.net',
                    'id' => 'AUDIO1',
                    'fromMe' => false,
                ],
                'pushName' => 'Cliente',
                'messageType' => 'audioMessage',
                'messageTimestamp' => now()->timestamp,
                'message' => [
                    'audioMessage' => [
                        'mimetype' => 'audio/ogg; codecs=opus',
                    ],
                ],
            ],
        ], dispatchSuggestions: false, withMedia: false);

        $this->assertSame('Transcrição existente do áudio.', $message->refresh()->text);
    }

    public function test_it_reuses_existing_conversation_when_same_phone_arrives_with_different_jid(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.default_company_id' => $companyId,
            'services.evolution.instance' => 'geolead_funnel_2',
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '138993832345808@lid',
            'phone_number' => '558199116613',
            'push_name' => 'Felipe Franca',
            'status' => 'open',
            'last_message_at' => now()->subMinute(),
        ]);

        app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'geolead_funnel_2',
            'sender' => '558199116613@s.whatsapp.net',
            'date_time' => now()->toISOString(),
            'data' => [
                'key' => [
                    'remoteJid' => '558199116613@s.whatsapp.net',
                    'id' => 'PHONEJID1',
                    'fromMe' => true,
                ],
                'messageType' => 'conversation',
                'messageTimestamp' => now()->timestamp,
                'message' => [
                    'conversation' => 'Mensagem enviada pelo JID numerico.',
                ],
            ],
        ], dispatchSuggestions: false);

        $message = WhatsappMessage::allTenants()->where('remote_message_id', 'PHONEJID1')->firstOrFail();

        $this->assertSame($conversation->id, $message->whatsapp_conversation_id);
        $this->assertSame('138993832345808@lid', $conversation->refresh()->remote_jid);
        $this->assertSame(1, WhatsappConversation::allTenants()->count());
    }

    public function test_it_matches_lid_remote_jid_alt_and_the_brazilian_ninth_digit_without_duplicates(): void
    {
        $user = User::factory()->create();
        config(['services.evolution.default_company_id' => $user->company->id]);

        $conversation = WhatsappConversation::create([
            'company_id' => $user->company->id,
            'name' => 'Diego CTO TM',
            'evolution_instance' => 'inst',
            'remote_jid' => '111111111111111@lid',
            'phone_number' => '5554999371490',
            'status' => 'open',
        ]);

        $handled = app(EvolutionWebhookHandler::class)->handle([
            'event' => 'messages.upsert',
            'instance' => 'inst',
            'sender' => '555499371490@s.whatsapp.net',
            'data' => [
                'key' => [
                    'remoteJid' => '222222222222222@lid',
                    'remoteJidAlt' => '555499371490@s.whatsapp.net',
                    'id' => 'LID-ALT-1',
                    'fromMe' => false,
                ],
                'pushName' => 'Diego remoto',
                'messageType' => 'conversation',
                'messageTimestamp' => now()->timestamp,
                'message' => ['conversation' => 'Mensagem pelo LID novo.'],
            ],
        ]);

        $this->assertSame(1, $handled);
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_conversation_id' => $conversation->id,
            'remote_message_id' => 'LID-ALT-1',
        ]);
        $this->assertDatabaseCount('whatsapp_conversations', 1);
        $this->assertSame('Diego CTO TM', $conversation->refresh()->name);
    }
}
