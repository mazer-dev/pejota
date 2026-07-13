<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappConversation;
use App\Services\Evolution\EvolutionApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EvolutionApiClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_instance_options_from_evolution_instances(): void
    {
        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'fallback_instance',
        ]);

        Http::fake([
            'http://evolution.test/instance/fetchInstances' => Http::response([
                [
                    'name' => 'geolead_funnel_2',
                    'profileName' => 'Luiz Fernando',
                    'connectionStatus' => 'open',
                ],
            ]),
        ]);

        $options = app(EvolutionApiClient::class)->instanceOptions();

        $this->assertSame('geolead_funnel_2 (Luiz Fernando - open)', $options['geolead_funnel_2']);
        $this->assertSame('fallback_instance', $options['fallback_instance']);
    }

    public function test_it_sends_text_using_conversation_instance(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'fallback_instance',
        ]);

        Http::fake([
            'http://evolution.test/message/sendText/client_instance' => Http::response([
                'key' => ['id' => 'MSG123'],
            ]),
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'evolution_instance' => 'client_instance',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'status' => 'open',
        ]);

        app(EvolutionApiClient::class)->sendText($conversation, 'Oi');

        Http::assertSent(fn ($request) => $request->url() === 'http://evolution.test/message/sendText/client_instance'
            && $request->hasHeader('apikey', 'secret')
            && $request['number'] === '5511999990000'
            && $request['text'] === 'Oi');
    }

    public function test_it_sends_media_using_conversation_instance(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'fallback_instance',
        ]);

        Http::fake([
            'http://evolution.test/message/sendMedia/client_instance' => Http::response([
                'key' => ['id' => 'MEDIA123'],
            ]),
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'evolution_instance' => 'client_instance',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'status' => 'open',
        ]);

        app(EvolutionApiClient::class)->sendMedia($conversation, 'ZmFrZQ==', 'image/png', 'foto.png', 'Legenda');

        Http::assertSent(fn ($request) => $request->url() === 'http://evolution.test/message/sendMedia/client_instance'
            && $request->hasHeader('apikey', 'secret')
            && $request['number'] === '5511999990000'
            && $request['mediatype'] === 'image'
            && $request['mimetype'] === 'image/png'
            && $request['caption'] === 'Legenda'
            && $request['media'] === 'ZmFrZQ=='
            && $request['fileName'] === 'foto.png');
    }

    public function test_it_downloads_media_base64_from_message(): void
    {
        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'fallback_instance',
        ]);

        Http::fake([
            'http://evolution.test/chat/getBase64FromMediaMessage/client_instance' => Http::response([
                'base64' => 'data:image/png;base64,ZmFrZQ==',
            ]),
        ]);

        $media = app(EvolutionApiClient::class)->getBase64FromMediaMessage('client_instance', [
            'key' => [
                'id' => 'MEDIA123',
                'remoteJid' => '5511999990000@s.whatsapp.net',
            ],
        ]);

        $this->assertSame([
            'mime_type' => 'image/png',
            'data' => 'ZmFrZQ==',
        ], $media);
    }

    public function test_it_sends_text_directly_to_a_number_and_instance(): void
    {
        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'fallback_instance',
        ]);

        Http::fake([
            'http://evolution.test/message/sendText/Assistente_Pejota' => Http::response([
                'key' => ['id' => 'MSG456'],
            ]),
        ]);

        app(EvolutionApiClient::class)->sendTextToNumber('Assistente_Pejota', '5554999371490', 'Olá!');

        Http::assertSent(fn ($request) => $request->url() === 'http://evolution.test/message/sendText/Assistente_Pejota'
            && $request->hasHeader('apikey', 'secret')
            && $request['number'] === '5554999371490'
            && $request['text'] === 'Olá!');
    }

    public function test_it_configures_webhook_for_an_explicit_instance(): void
    {
        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'client_instance',
        ]);

        Http::fake([
            'http://evolution.test/webhook/set/Assistente_Pejota' => Http::response([
                'enabled' => true,
            ]),
        ]);

        app(EvolutionApiClient::class)->setWebhook('https://pejota.test/webhooks/evolution?token=abc', true, 'Assistente_Pejota');

        Http::assertSent(fn ($request) => $request->url() === 'http://evolution.test/webhook/set/Assistente_Pejota'
            && $request['webhook']['url'] === 'https://pejota.test/webhooks/evolution?token=abc'
            && $request['webhook']['base64'] === true);
    }

    public function test_it_configures_webhook_with_evolution_payload_keys(): void
    {
        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'client_instance',
        ]);

        Http::fake([
            'http://evolution.test/webhook/set/client_instance' => Http::response([
                'enabled' => true,
                'webhookBase64' => true,
            ]),
        ]);

        app(EvolutionApiClient::class)->setWebhook('https://pejota.test/webhooks/evolution?token=abc', true);

        Http::assertSent(fn ($request) => $request->url() === 'http://evolution.test/webhook/set/client_instance'
            && $request->hasHeader('apikey', 'secret')
            && $request['webhook']['enabled'] === true
            && $request['webhook']['url'] === 'https://pejota.test/webhooks/evolution?token=abc'
            && $request['webhook']['byEvents'] === false
            && $request['webhook']['base64'] === true
            && $request['webhook']['events'] === [
                'MESSAGES_UPSERT',
                'MESSAGES_UPDATE',
                'SEND_MESSAGE',
            ]);
    }
}
