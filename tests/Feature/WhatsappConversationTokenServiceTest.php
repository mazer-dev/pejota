<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Evolution\WhatsappConversationTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappConversationTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_tokens_using_the_luiz_label_and_full_history(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;
        $this->actingAs($user);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'evolution_instance' => 'client_instance',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'status' => 'open',
        ]);

        WhatsappMessage::create([
            'company_id' => $companyId,
            'whatsapp_conversation_id' => $conversation->id,
            'evolution_instance' => 'client_instance',
            'remote_message_id' => 'MSG1',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'sender_name' => 'Vivianne',
            'from_me' => false,
            'message_type' => 'chat',
            'text' => 'Oi, tudo bem?',
            'sent_at' => now()->subMinutes(5),
        ]);

        WhatsappMessage::create([
            'company_id' => $companyId,
            'whatsapp_conversation_id' => $conversation->id,
            'evolution_instance' => 'client_instance',
            'remote_message_id' => 'MSG2',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'from_me' => true,
            'message_type' => 'chat',
            'text' => 'Tudo certo, e você?',
            'sent_at' => now(),
        ]);

        $tokens = app(WhatsappConversationTokenService::class)->refresh($conversation);

        $this->assertGreaterThan(0, $tokens);

        $conversation->refresh();
        $this->assertSame($tokens, $conversation->context_tokens);
        $this->assertNotNull($conversation->context_updated_at);
    }
}
