<?php

namespace Tests\Feature\Assistant;

use App\Jobs\ProcessAssistantMessage;
use App\Livewire\AssistantChat;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\AssistantChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AssistantChatComponentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_sending_a_message_persists_it_and_dispatches_the_job(): void
    {
        Bus::fake();

        Livewire::test(AssistantChat::class)
            ->set('message', 'Quantas tarefas abertas eu tenho?')
            ->call('send')
            ->assertSet('message', '');

        $conversation = AssistantConversation::first();

        $this->assertNotNull($conversation);
        $this->assertSame($this->user->company->id, $conversation->company_id);
        $this->assertSame($this->user->id, $conversation->user_id);

        $this->assertDatabaseHas('assistant_messages', [
            'assistant_conversation_id' => $conversation->id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => 'Quantas tarefas abertas eu tenho?',
        ]);

        Bus::assertDispatched(
            ProcessAssistantMessage::class,
            fn (ProcessAssistantMessage $job): bool => $job->conversation->is($conversation) && $job->user->is($this->user),
        );
    }

    public function test_assistant_messages_render_markdown_and_strip_raw_html(): void
    {
        $conversation = AssistantConversation::create([
            'company_id' => $this->user->company->id,
            'user_id' => $this->user->id,
            'title' => 'Resumo',
        ]);

        $conversation->messages()->create([
            'company_id' => $this->user->company->id,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => "**Horas:** 84 minutos\n\n<script>alert(1)</script>",
        ]);

        Livewire::test(AssistantChat::class)
            ->call('continueConversation', $conversation->id)
            ->assertSeeHtml('<strong>Horas:</strong>')
            ->assertDontSeeHtml('<script>');
    }

    public function test_user_messages_stay_escaped_plain_text(): void
    {
        $conversation = AssistantConversation::create([
            'company_id' => $this->user->company->id,
            'user_id' => $this->user->id,
            'title' => 'Oi',
        ]);

        $conversation->messages()->create([
            'company_id' => $this->user->company->id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => '**negrito** <b>html</b>',
        ]);

        Livewire::test(AssistantChat::class)
            ->call('continueConversation', $conversation->id)
            ->assertSee('**negrito**')
            ->assertDontSeeHtml('<b>html</b>');
    }

    public function test_blank_messages_are_ignored(): void
    {
        Bus::fake();

        Livewire::test(AssistantChat::class)
            ->set('message', '   ')
            ->call('send');

        $this->assertDatabaseCount('assistant_conversations', 0);
        Bus::assertNothingDispatched();
    }

    public function test_chip_answers_instantly_without_dispatching_a_job(): void
    {
        Bus::fake();

        Livewire::test(AssistantChat::class)
            ->call('runChip', 'today');

        Bus::assertNothingDispatched();

        $conversation = AssistantConversation::first();
        $this->assertNotNull($conversation);
        $this->assertSame(
            [AssistantMessage::ROLE_USER, AssistantMessage::ROLE_ASSISTANT],
            $conversation->messages()->pluck('role')->all(),
        );
    }

    public function test_new_conversation_resets_and_continue_restores(): void
    {
        Bus::fake();

        $component = Livewire::test(AssistantChat::class)
            ->set('message', 'Primeira pergunta')
            ->call('send');

        $conversation = AssistantConversation::first();

        $component->call('newConversation')
            ->assertSet('conversationId', null)
            ->call('continueConversation', $conversation->id)
            ->assertSet('conversationId', $conversation->id)
            ->assertSee('Primeira pergunta');
    }

    public function test_job_appends_the_assistant_answer_to_the_conversation(): void
    {
        $conversation = AssistantConversation::create([
            'company_id' => $this->user->company->id,
            'user_id' => $this->user->id,
            'title' => 'Pergunta',
        ]);
        $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => 'Oi',
        ]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn('{"say": "Olá! Pergunte sobre seus dados."}');
        $this->instance(AiCliRunner::class, $runner);

        (new ProcessAssistantMessage($conversation, $this->user))->handle(app(AssistantChatService::class));

        $this->assertDatabaseHas('assistant_messages', [
            'assistant_conversation_id' => $conversation->id,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => 'Olá! Pergunte sobre seus dados.',
        ]);
    }
}
