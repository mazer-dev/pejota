<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Resources\WhatsappConversationResource\Pages\ViewWhatsappConversation;
use App\Filament\App\Resources\WhatsappConversationResource\RelationManagers\MessagesRelationManager;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Services\Ai\CliWhatsappConversationQuestionAnswerer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WhatsappAskAiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_temporary_modal_answers_without_touching_the_composer_and_resets_on_close(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $conversation = $this->conversation($user, 'João — Financeiro');

        $answerer = Mockery::mock(CliWhatsappConversationQuestionAnswerer::class);
        $answerer->shouldReceive('answer')
            ->once()
            ->with(Mockery::on(fn (WhatsappConversation $record): bool => $record->is($conversation)), 'Qual prazo foi combinado?')
            ->andReturn('O prazo salvo é sexta-feira.');
        $this->instance(CliWhatsappConversationQuestionAnswerer::class, $answerer);

        Livewire::test(MessagesRelationManager::class, [
            'ownerRecord' => $conversation,
            'pageClass' => ViewWhatsappConversation::class,
        ])
            ->set('composerMessage', 'Rascunho intacto')
            ->call('openAiQuestionModal')
            ->assertSet('showAiQuestionModal', true)
            ->set('aiQuestion', 'Qual prazo foi combinado?')
            ->call('askAiQuestion')
            ->assertSet('aiAnswer', 'O prazo salvo é sexta-feira.')
            ->assertSet('composerMessage', 'Rascunho intacto')
            ->call('closeAiQuestionModal')
            ->assertSet('showAiQuestionModal', false)
            ->assertSet('aiQuestion', '')
            ->assertSet('aiAnswer', null);
    }

    public function test_it_rejects_blank_and_oversized_questions_and_keeps_failures_in_the_current_modal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $conversation = $this->conversation($user, 'Diego CTO TM');

        $answerer = Mockery::mock(CliWhatsappConversationQuestionAnswerer::class);
        $answerer->shouldReceive('answer')->once()->andThrow(new RuntimeException('IA indisponível'));
        $this->instance(CliWhatsappConversationQuestionAnswerer::class, $answerer);

        $component = Livewire::test(MessagesRelationManager::class, [
            'ownerRecord' => $conversation,
            'pageClass' => ViewWhatsappConversation::class,
        ])->call('openAiQuestionModal');

        $component->set('aiQuestion', '')->call('askAiQuestion')->assertHasErrors(['aiQuestion' => 'required']);
        $component->set('aiQuestion', str_repeat('a', 4001))->call('askAiQuestion')->assertHasErrors(['aiQuestion' => 'max']);
        $component->set('aiQuestion', 'Uma pergunta válida')->call('askAiQuestion')
            ->assertSet('showAiQuestionModal', true)
            ->assertSet('aiAnswer', null)
            ->assertNotified();
    }

    private function conversation(User $user, string $name): WhatsappConversation
    {
        return WhatsappConversation::create([
            'company_id' => $user->company->id,
            'name' => $name,
            'evolution_instance' => 'inst',
            'remote_jid' => fake()->unique()->numerify('#############').'@s.whatsapp.net',
            'phone_number' => fake()->unique()->numerify('#############'),
            'status' => 'open',
        ]);
    }
}
