<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Resources\WhatsappConversationResource\Pages\ViewWhatsappConversation;
use App\Filament\App\Resources\WhatsappConversationResource\RelationManagers\MessagesRelationManager;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsappMessageEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WhatsappConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        config([
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
        ]);

        $this->conversation = WhatsappConversation::create([
            'company_id' => $this->user->company->id,
            'evolution_instance' => 'inst',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'push_name' => 'Vivianne',
            'status' => 'open',
        ]);
    }

    private function makeOwnMessage(array $overrides = []): WhatsappMessage
    {
        return WhatsappMessage::create(array_merge([
            'company_id' => $this->conversation->company_id,
            'whatsapp_conversation_id' => $this->conversation->id,
            'evolution_instance' => 'inst',
            'remote_message_id' => 'MSG1',
            'remote_jid' => $this->conversation->remote_jid,
            'from_me' => true,
            'message_type' => 'text',
            'text' => 'Mensagem original',
            'status' => 'sent',
            'sent_at' => now(),
        ], $overrides));
    }

    private function mountRelationManager(): Testable
    {
        return Livewire::test(MessagesRelationManager::class, [
            'ownerRecord' => $this->conversation,
            'pageClass' => ViewWhatsappConversation::class,
        ]);
    }

    public function test_it_edits_an_own_message_on_whatsapp_and_locally(): void
    {
        $message = $this->makeOwnMessage();

        Http::preventStrayRequests();
        Http::fake([
            'http://evolution.test/chat/updateMessage/inst' => Http::response(['status' => 'ok']),
        ]);

        $this->mountRelationManager()
            ->call('startEditingMessage', $message->id)
            ->assertSet('editingMessageId', $message->id)
            ->set('editingMessageText', 'Mensagem corrigida')
            ->call('saveEditedMessage')
            ->assertSet('editingMessageId', null)
            ->assertNotified();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://evolution.test/chat/updateMessage/inst'
                && data_get($request->data(), 'key.id') === 'MSG1'
                && data_get($request->data(), 'text') === 'Mensagem corrigida';
        });

        $this->assertSame('Mensagem corrigida', $message->refresh()->text);
    }

    public function test_it_blocks_editing_outside_the_whatsapp_window(): void
    {
        $message = $this->makeOwnMessage(['sent_at' => now()->subMinutes(30)]);

        Http::preventStrayRequests();
        Http::fake();

        $this->mountRelationManager()
            ->call('startEditingMessage', $message->id)
            ->assertSet('editingMessageId', null)
            ->assertNotified();

        Http::assertNothingSent();
        $this->assertSame('Mensagem original', $message->refresh()->text);
    }

    public function test_it_deletes_an_own_message_on_whatsapp_and_locally(): void
    {
        $message = $this->makeOwnMessage();

        Http::preventStrayRequests();
        Http::fake([
            'http://evolution.test/chat/deleteMessageForEveryone/inst' => Http::response(['status' => 'ok']),
        ]);

        $this->mountRelationManager()
            ->call('deleteMessage', $message->id)
            ->assertNotified();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://evolution.test/chat/deleteMessageForEveryone/inst'
                && data_get($request->data(), 'id') === 'MSG1';
        });

        $this->assertDatabaseMissing('whatsapp_messages', ['id' => $message->id]);
    }

    public function test_it_keeps_the_message_when_whatsapp_refuses_the_deletion(): void
    {
        $message = $this->makeOwnMessage();

        Http::preventStrayRequests();
        Http::fake([
            'http://evolution.test/chat/deleteMessageForEveryone/inst' => Http::response(['message' => 'out of window'], 400),
        ]);

        $this->mountRelationManager()
            ->call('deleteMessage', $message->id)
            ->assertNotified();

        $this->assertDatabaseHas('whatsapp_messages', ['id' => $message->id]);
    }

    public function test_it_refuses_to_edit_or_delete_client_messages(): void
    {
        $message = $this->makeOwnMessage(['from_me' => false, 'remote_message_id' => 'CLIENT1']);

        Http::preventStrayRequests();
        Http::fake();

        $this->mountRelationManager()
            ->call('startEditingMessage', $message->id)
            ->assertSet('editingMessageId', null)
            ->call('deleteMessage', $message->id)
            ->assertNotified();

        Http::assertNothingSent();
        $this->assertDatabaseHas('whatsapp_messages', ['id' => $message->id]);
    }
}
