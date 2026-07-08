<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Resources\WhatsappConversationResource\Pages\ViewWhatsappConversation;
use App\Filament\App\Resources\WhatsappConversationResource\RelationManagers\MessagesRelationManager;
use App\Models\Client;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Services\Ai\CliWhatsappMessageSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class WhatsappDictateMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WhatsappConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $client = Client::create([
            'company_id' => $this->user->company->id,
            'name' => 'Vivianne',
        ]);

        $this->conversation = WhatsappConversation::create([
            'company_id' => $this->user->company->id,
            'client_id' => $client->id,
            'evolution_instance' => 'inst',
            'remote_jid' => 'a@s.whatsapp.net',
            'status' => 'open',
        ]);
    }

    public function test_an_instruction_generates_a_message_into_the_composer(): void
    {
        $suggester = Mockery::mock(CliWhatsappMessageSuggester::class);
        $suggester->shouldReceive('suggest')
            ->once()
            ->with(
                Mockery::on(fn (WhatsappConversation $conversation): bool => $conversation->is($this->conversation)),
                '',
                'avisar que a entrega atrasa 2 dias',
            )
            ->andReturn('Oi Vivianne! A entrega vai precisar de mais 2 dias.');
        $this->instance(CliWhatsappMessageSuggester::class, $suggester);

        Livewire::test(MessagesRelationManager::class, [
            'ownerRecord' => $this->conversation,
            'pageClass' => ViewWhatsappConversation::class,
        ])
            ->set('aiInstruction', 'avisar que a entrega atrasa 2 dias')
            ->call('generateAiFromInstruction')
            ->assertSet('composerMessage', 'Oi Vivianne! A entrega vai precisar de mais 2 dias.')
            ->assertSet('aiInstruction', '')
            ->assertNotified();
    }

    public function test_a_blank_instruction_warns_and_calls_no_ai(): void
    {
        $suggester = Mockery::mock(CliWhatsappMessageSuggester::class);
        $suggester->shouldNotReceive('suggest');
        $this->instance(CliWhatsappMessageSuggester::class, $suggester);

        Livewire::test(MessagesRelationManager::class, [
            'ownerRecord' => $this->conversation,
            'pageClass' => ViewWhatsappConversation::class,
        ])
            ->set('composerMessage', 'texto que já estava lá')
            ->set('aiInstruction', '   ')
            ->call('generateAiFromInstruction')
            ->assertSet('composerMessage', 'texto que já estava lá')
            ->assertNotified();
    }
}
