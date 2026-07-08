<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Resources\TaskResource\Pages\ViewTask;
use App\Filament\App\Resources\WhatsappConversationResource\Pages\ViewWhatsappConversation;
use App\Filament\App\Resources\WhatsappConversationResource\RelationManagers\MessagesRelationManager;
use App\Models\Client;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Services\Ai\AiCliRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class TaskDraftMessageActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeTask(?Client $client = null): Task
    {
        $status = Status::create([
            'name' => 'A Fazer', 'phase' => 'todo', 'color' => '#000', 'sort_order' => 1, 'active' => true,
            'company_id' => $this->user->company->id,
        ]);

        return Task::create([
            'title' => 'Obter convite de acesso ao HubSpot',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'client_id' => $client?->id,
        ]);
    }

    private function makeConversation(Client $client, array $attributes = []): WhatsappConversation
    {
        return WhatsappConversation::create(array_merge([
            'company_id' => $this->user->company->id,
            'client_id' => $client->id,
            'evolution_instance' => 'inst',
            'remote_jid' => uniqid().'@s.whatsapp.net',
            'status' => 'open',
        ], $attributes));
    }

    public function test_the_action_is_hidden_when_the_task_has_no_client(): void
    {
        $task = $this->makeTask();

        Livewire::test(ViewTask::class, ['record' => $task->id])
            ->assertInfolistActionHidden('taskAiActions', 'aiDraftMessage');
    }

    public function test_it_applies_the_waiting_tag_stores_the_draft_and_redirects_to_the_latest_conversation(): void
    {
        $client = Client::create(['company_id' => $this->user->company->id, 'name' => 'Vivianne']);
        $task = $this->makeTask($client);

        $older = $this->makeConversation($client, ['last_message_at' => now()->subDays(9)]);
        $latest = $this->makeConversation($client, ['last_message_at' => now()->subDay()]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn('Oi Vivianne! Consegue me mandar o convite do HubSpot?');
        $this->instance(AiCliRunner::class, $runner);

        Livewire::test(ViewTask::class, ['record' => $task->id])
            ->callInfolistAction('taskAiActions', 'aiDraftMessage')
            ->assertRedirect(ViewWhatsappConversation::getUrl([$latest->id]));

        $this->assertTrue($task->fresh()->tags->pluck('name')->contains(Task::TAG_WAITING_CLIENT));

        $this->assertSame(
            'Oi Vivianne! Consegue me mandar o convite do HubSpot?',
            session()->get(MessagesRelationManager::draftSessionKey($latest->id)),
        );
        $this->assertNull(session()->get(MessagesRelationManager::draftSessionKey($older->id)));
    }

    public function test_disabling_the_toggle_skips_the_waiting_tag(): void
    {
        $client = Client::create(['company_id' => $this->user->company->id, 'name' => 'Vivianne']);
        $task = $this->makeTask($client);
        $this->makeConversation($client, ['last_message_at' => now()]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn('Rascunho.');
        $this->instance(AiCliRunner::class, $runner);

        Livewire::test(ViewTask::class, ['record' => $task->id])
            ->callInfolistAction('taskAiActions', 'aiDraftMessage', data: [
                'mark_waiting' => false,
            ]);

        $this->assertFalse($task->fresh()->tags->pluck('name')->contains(Task::TAG_WAITING_CLIENT));
    }

    public function test_without_a_conversation_it_only_tags_and_does_not_redirect(): void
    {
        $client = Client::create(['company_id' => $this->user->company->id, 'name' => 'Sem conversa']);
        $task = $this->makeTask($client);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn('Rascunho.');
        $this->instance(AiCliRunner::class, $runner);

        Livewire::test(ViewTask::class, ['record' => $task->id])
            ->callInfolistAction('taskAiActions', 'aiDraftMessage')
            ->assertNoRedirect();

        $this->assertTrue($task->fresh()->tags->pluck('name')->contains(Task::TAG_WAITING_CLIENT));
    }

    public function test_the_conversation_composer_consumes_the_stored_draft_on_mount(): void
    {
        $client = Client::create(['company_id' => $this->user->company->id, 'name' => 'Vivianne']);
        $conversation = $this->makeConversation($client);

        session()->put(MessagesRelationManager::draftSessionKey($conversation), 'Rascunho vindo da tarefa.');

        Livewire::test(MessagesRelationManager::class, [
            'ownerRecord' => $conversation,
            'pageClass' => ViewWhatsappConversation::class,
        ])->assertSet('composerMessage', 'Rascunho vindo da tarefa.');

        $this->assertNull(session()->get(MessagesRelationManager::draftSessionKey($conversation)));
    }

    public function test_the_composer_stays_empty_without_a_stored_draft(): void
    {
        $client = Client::create(['company_id' => $this->user->company->id, 'name' => 'Vivianne']);
        $conversation = $this->makeConversation($client);

        Livewire::test(MessagesRelationManager::class, [
            'ownerRecord' => $conversation,
            'pageClass' => ViewWhatsappConversation::class,
        ])->assertSet('composerMessage', '');
    }
}
