<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Services\Evolution\WhatsappConversationSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappConversationSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_lid_chat_and_imports_messages_into_existing_conversation(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;

        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.default_company_id' => $companyId,
        ]);

        $client = Client::create([
            'company_id' => $companyId,
            'name' => 'Vivianne',
            'phone' => '+55 62 98174-9881',
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'client_id' => $client->id,
            'evolution_instance' => 'geolead_funnel_2',
            'remote_jid' => '5562981749881@s.whatsapp.net',
            'phone_number' => '+55 62 98174-9881',
            'push_name' => 'Vivianne',
            'status' => 'open',
        ]);

        Http::fake(function (Request $request) {
            if ($request->url() === 'http://evolution.test/chat/findChats/geolead_funnel_2') {
                return Http::response([
                    [
                        'remoteJid' => '274422674006059@lid',
                        'pushName' => null,
                        'lastMessage' => [
                            'pushName' => 'Vivianne Oliveira',
                            'key' => [
                                'remoteJid' => '274422674006059@lid',
                                'remoteJidAlt' => '556281749881@s.whatsapp.net',
                            ],
                        ],
                    ],
                ]);
            }

            if ($request->url() === 'http://evolution.test/chat/findContacts/geolead_funnel_2') {
                return Http::response([]);
            }

            if ($request->url() === 'http://evolution.test/chat/findMessages/geolead_funnel_2') {
                $remoteJid = data_get($request->data(), 'where.key.remoteJid');

                if ($remoteJid === '5562981749881@s.whatsapp.net') {
                    return Http::response(['messages' => ['records' => []]]);
                }

                return Http::response([
                    'messages' => [
                        'records' => [
                            [
                                'key' => [
                                    'id' => 'NEW',
                                    'fromMe' => false,
                                    'remoteJid' => '274422674006059@lid',
                                    'remoteJidAlt' => '556281749881@s.whatsapp.net',
                                ],
                                'pushName' => 'Vivianne Oliveira',
                                'messageType' => 'conversation',
                                'messageTimestamp' => 200,
                                'message' => ['conversation' => 'ok'],
                            ],
                            [
                                'key' => [
                                    'id' => 'OLD',
                                    'fromMe' => true,
                                    'remoteJid' => '274422674006059@lid',
                                    'remoteJidAlt' => '556281749881@s.whatsapp.net',
                                ],
                                'pushName' => 'Luiz Fernando',
                                'messageType' => 'conversation',
                                'messageTimestamp' => 100,
                                'message' => ['conversation' => 'Enquanto isso, vou analisando.'],
                            ],
                        ],
                    ],
                ]);
            }

            return Http::response([], 404);
        });

        $count = app(WhatsappConversationSyncService::class)->sync($conversation);

        $conversation->refresh();

        $this->assertSame(2, $count);
        $this->assertSame('274422674006059@lid', $conversation->remote_jid);
        $this->assertSame('556281749881', $conversation->phone_number);
        $this->assertSame(Carbon::createFromTimestamp(200)->toDateTimeString(), $conversation->last_message_at?->toDateTimeString());
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_conversation_id' => $conversation->id,
            'remote_message_id' => 'NEW',
            'text' => 'ok',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_conversation_id' => $conversation->id,
            'remote_message_id' => 'OLD',
            'text' => 'Enquanto isso, vou analisando.',
        ]);
    }

    public function test_it_can_skip_candidate_discovery_for_light_polling(): void
    {
        $user = User::factory()->create();

        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'client_instance',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'http://evolution.test/chat/findMessages/client_instance' => Http::response([
                'messages' => [
                    'records' => [],
                ],
            ]),
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $user->company->id,
            'evolution_instance' => 'client_instance',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'status' => 'open',
        ]);

        $count = app(WhatsappConversationSyncService::class)->sync($conversation, discoverCandidates: false);

        $this->assertSame(0, $count);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'http://evolution.test/chat/findMessages/client_instance');
    }

    public function test_light_polling_does_not_download_media_nor_enrich_attachments(): void
    {
        $user = User::factory()->create();

        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
            'services.evolution.instance' => 'client_instance',
            'services.evolution.default_company_id' => $user->company->id,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'http://evolution.test/chat/findMessages/client_instance' => Http::response([
                'messages' => [
                    'records' => [
                        [
                            'key' => [
                                'id' => 'AUDIO1',
                                'fromMe' => false,
                                'remoteJid' => '5511999990000@s.whatsapp.net',
                            ],
                            'pushName' => 'Cliente',
                            'messageType' => 'audioMessage',
                            'messageTimestamp' => 100,
                            'message' => [
                                'audioMessage' => [
                                    'mimetype' => 'audio/ogg; codecs=opus',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $user->company->id,
            'evolution_instance' => 'client_instance',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'status' => 'open',
        ]);

        $count = app(WhatsappConversationSyncService::class)
            ->sync($conversation, discoverCandidates: false, withMedia: false);

        $this->assertSame(1, $count);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('whatsapp_attachments', [
            'mime_type' => 'audio/ogg; codecs=opus',
            'path' => null,
            'status' => 'metadata_only',
        ]);
    }
}
