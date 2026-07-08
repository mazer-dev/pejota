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
}
