<?php

namespace Tests\Feature\Filament;

use App\Enums\CompanySettingsEnum;
use App\Enums\WhatsappSuggestionStatusEnum;
use App\Enums\WhatsappSuggestionTypeEnum;
use App\Filament\App\Resources\WhatsappConversationResource\Pages\ViewWhatsappConversation;
use App\Filament\App\Resources\WhatsappConversationResource\RelationManagers\MessagesRelationManager;
use App\Models\Client;
use App\Models\Note;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsappSuggestionActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Project $project;

    private WhatsappConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $companyId = $this->user->company->id;

        $this->client = Client::create([
            'company_id' => $companyId,
            'name' => 'Vivianne',
        ]);

        $this->project = Project::create([
            'company_id' => $companyId,
            'client_id' => $this->client->id,
            'name' => 'Configuração de E-mails',
        ]);

        $this->conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'evolution_instance' => 'inst',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'push_name' => 'Vivianne',
            'status' => 'open',
        ]);
    }

    private function makeTodoStatus(): Status
    {
        return Status::create([
            'name' => 'To Do',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->user->company->id,
        ]);
    }

    private function makeSuggestion(WhatsappSuggestionTypeEnum $type, ?WhatsappConversation $conversation = null): WhatsappSuggestion
    {
        $conversation ??= $this->conversation;

        return WhatsappSuggestion::create([
            'company_id' => $conversation->company_id,
            'whatsapp_conversation_id' => $conversation->id,
            'client_id' => $conversation->client_id,
            'project_id' => $conversation->project_id,
            'type' => $type,
            'title' => $type === WhatsappSuggestionTypeEnum::Task ? 'Criar relatório novo' : 'Credencial do painel',
            'content' => $type === WhatsappSuggestionTypeEnum::Task ? 'Cliente confirmou o relatório.' : 'Senha do painel: hunter2.',
            'status' => WhatsappSuggestionStatusEnum::Pending,
        ]);
    }

    private function mountRelationManager(): Testable
    {
        return Livewire::test(MessagesRelationManager::class, [
            'ownerRecord' => $this->conversation,
            'pageClass' => ViewWhatsappConversation::class,
        ]);
    }

    public function test_pending_suggestions_are_rendered_with_accept_and_dismiss_actions(): void
    {
        $this->user->company->settings()->set(CompanySettingsEnum::LOCALIZATION_TIMEZONE->value, 'America/Sao_Paulo');

        WhatsappMessage::create([
            'company_id' => $this->user->company->id,
            'whatsapp_conversation_id' => $this->conversation->id,
            'client_id' => $this->client->id,
            'evolution_instance' => 'inst',
            'remote_message_id' => 'MSG1',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'from_me' => false,
            'message_type' => 'text',
            'text' => 'Preciso de um relatório novo.',
            'sent_at' => now(),
        ]);

        $this->makeSuggestion(WhatsappSuggestionTypeEnum::Task);

        $this->mountRelationManager()
            ->assertSee('Sugestões da IA')
            ->assertSee('Criar relatório novo')
            ->assertSee('Aceitar')
            ->assertSee('Descartar');
    }

    public function test_accepting_a_task_suggestion_creates_a_task_with_todo_status(): void
    {
        $status = $this->makeTodoStatus();
        $suggestion = $this->makeSuggestion(WhatsappSuggestionTypeEnum::Task);

        $this->mountRelationManager()
            ->call('acceptSuggestion', $suggestion->id)
            ->assertNotified();

        $task = Task::query()->first();
        $this->assertNotNull($task);
        $this->assertSame('Criar relatório novo', $task->title);
        $this->assertSame($this->client->id, $task->client_id);
        $this->assertSame($this->project->id, $task->project_id);
        $this->assertSame($status->id, $task->status_id);
        $this->assertStringContainsString('Cliente confirmou o relatório.', $task->description);
        $this->assertStringContainsString('conversa de WhatsApp com Vivianne', $task->description);

        $suggestion->refresh();
        $this->assertSame(WhatsappSuggestionStatusEnum::Accepted, $suggestion->status);
        $this->assertSame($task->id, $suggestion->task_id);
        $this->assertNotNull($suggestion->accepted_at);
    }

    public function test_accepting_a_note_suggestion_creates_a_note(): void
    {
        $suggestion = $this->makeSuggestion(WhatsappSuggestionTypeEnum::Note);

        $this->mountRelationManager()
            ->call('acceptSuggestion', $suggestion->id)
            ->assertNotified();

        $note = Note::query()->first();
        $this->assertNotNull($note);
        $this->assertSame('Credencial do painel', $note->title);
        $this->assertSame($this->client->id, $note->client_id);
        $this->assertSame($this->project->id, $note->project_id);
        $this->assertSame($this->user->id, $note->user_id);
        $this->assertSame('text', $note->content[0]['type']);
        $this->assertStringContainsString('Senha do painel: hunter2.', $note->content[0]['data']['content']);
        $this->assertStringContainsString('conversa de WhatsApp com Vivianne', $note->content[0]['data']['content']);

        $suggestion->refresh();
        $this->assertSame(WhatsappSuggestionStatusEnum::Accepted, $suggestion->status);
        $this->assertSame($note->id, $suggestion->note_id);
        $this->assertNotNull($suggestion->accepted_at);
    }

    public function test_accepting_a_task_suggestion_without_any_status_keeps_it_pending(): void
    {
        $suggestion = $this->makeSuggestion(WhatsappSuggestionTypeEnum::Task);

        $this->mountRelationManager()
            ->call('acceptSuggestion', $suggestion->id)
            ->assertNotified();

        $this->assertDatabaseCount('tasks', 0);
        $this->assertSame(WhatsappSuggestionStatusEnum::Pending, $suggestion->fresh()->status);
    }

    public function test_dismissing_a_suggestion_marks_it_dismissed_and_creates_nothing(): void
    {
        $this->makeTodoStatus();
        $suggestion = $this->makeSuggestion(WhatsappSuggestionTypeEnum::Task);

        $this->mountRelationManager()
            ->call('dismissSuggestion', $suggestion->id)
            ->assertNotified();

        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('notes', 0);

        $suggestion->refresh();
        $this->assertSame(WhatsappSuggestionStatusEnum::Dismissed, $suggestion->status);
        $this->assertNotNull($suggestion->dismissed_at);
        $this->assertNull($suggestion->task_id);
        $this->assertNull($suggestion->note_id);
    }

    public function test_it_ignores_suggestions_from_another_company_conversation(): void
    {
        $this->makeTodoStatus();

        $otherUser = User::factory()->create();

        $otherConversation = WhatsappConversation::create([
            'company_id' => $otherUser->company->id,
            'evolution_instance' => 'inst',
            'remote_jid' => '5511888880000@s.whatsapp.net',
            'push_name' => 'Outro Cliente',
            'status' => 'open',
        ]);

        $foreignSuggestion = $this->makeSuggestion(WhatsappSuggestionTypeEnum::Task, $otherConversation);

        $this->mountRelationManager()
            ->call('acceptSuggestion', $foreignSuggestion->id)
            ->assertNotified();

        $this->assertDatabaseCount('tasks', 0);
        $this->assertSame(WhatsappSuggestionStatusEnum::Pending, $foreignSuggestion->fresh()->status);
    }

    public function test_an_already_resolved_suggestion_cannot_be_accepted_again(): void
    {
        $this->makeTodoStatus();
        $suggestion = $this->makeSuggestion(WhatsappSuggestionTypeEnum::Task);

        $suggestion->forceFill([
            'status' => WhatsappSuggestionStatusEnum::Dismissed,
            'dismissed_at' => now(),
        ])->save();

        $this->mountRelationManager()
            ->call('acceptSuggestion', $suggestion->id)
            ->assertNotified();

        $this->assertDatabaseCount('tasks', 0);
        $this->assertSame(WhatsappSuggestionStatusEnum::Dismissed, $suggestion->fresh()->status);
    }
}
