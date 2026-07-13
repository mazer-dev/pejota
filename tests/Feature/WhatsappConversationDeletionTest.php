<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappAttachment;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsappConversationDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_conversation_through_eloquent_removes_children_and_physical_files(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $conversation = WhatsappConversation::create([
            'company_id' => $user->company->id,
            'name' => 'Luiz Hakan',
            'evolution_instance' => 'inst',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'status' => 'open',
        ]);

        $message = WhatsappMessage::create([
            'company_id' => $user->company->id,
            'whatsapp_conversation_id' => $conversation->id,
            'evolution_instance' => 'inst',
            'remote_message_id' => 'DELETE-1',
            'from_me' => false,
            'message_type' => 'image',
            'sent_at' => now(),
        ]);

        Storage::disk('local')->put('whatsapp/delete-me.png', 'image');
        WhatsappAttachment::create([
            'company_id' => $user->company->id,
            'whatsapp_message_id' => $message->id,
            'disk' => 'local',
            'path' => 'whatsapp/delete-me.png',
            'status' => 'stored',
        ]);

        WhatsappSuggestion::create([
            'company_id' => $user->company->id,
            'whatsapp_conversation_id' => $conversation->id,
            'whatsapp_message_id' => $message->id,
            'type' => 'note',
            'title' => 'Sugestão antiga',
            'content' => 'Conteúdo',
            'status' => 'pending',
        ]);

        $conversation->delete();

        $this->assertDatabaseCount('whatsapp_conversations', 0);
        $this->assertDatabaseCount('whatsapp_messages', 0);
        $this->assertDatabaseCount('whatsapp_attachments', 0);
        $this->assertDatabaseCount('whatsapp_suggestions', 0);
        Storage::disk('local')->assertMissing('whatsapp/delete-me.png');
    }
}
