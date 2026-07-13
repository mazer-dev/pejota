<?php

namespace Tests\Feature\Assistant;

use App\Jobs\ProcessAssistantMessage;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantMessageAttachment;
use App\Models\User;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\AssistantAttachmentUploader;
use App\Services\Ai\AssistantChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Feature\Assistant\Concerns\MakesAttachmentFixtures;
use Tests\TestCase;

class AssistantAttachmentJobTest extends TestCase
{
    use MakesAttachmentFixtures, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeConversationWithAttachment(): AssistantConversation
    {
        $conversation = AssistantConversation::create([
            'company_id' => $this->user->company->id,
            'user_id' => $this->user->id,
            'title' => 'Teste',
        ]);

        $message = $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => 'Analise os anexos e apresente um resumo dos pontos principais.',
        ]);

        app(AssistantAttachmentUploader::class)->persist($this->fakeTxt('notas.txt', 'Conteúdo do arquivo'), $message);

        return $conversation;
    }

    public function test_the_job_uses_the_attachments_timeout_when_the_last_user_message_has_attachments(): void
    {
        config(['services.assistant.attachments.timeout' => 900]);

        $conversation = $this->makeConversationWithAttachment();

        $job = new ProcessAssistantMessage($conversation, $this->user);

        $this->assertSame(900, $job->timeout);
    }

    public function test_the_job_keeps_the_plain_timeout_when_there_are_no_attachments(): void
    {
        $conversation = AssistantConversation::create([
            'company_id' => $this->user->company->id,
            'user_id' => $this->user->id,
            'title' => 'Sem anexos',
        ]);

        $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => 'Oi',
        ]);

        $job = new ProcessAssistantMessage($conversation, $this->user);

        $this->assertSame(420, $job->timeout);
    }

    public function test_the_job_processes_attachments_then_answers_and_appends_no_failure_notice_on_success(): void
    {
        $conversation = $this->makeConversationWithAttachment();

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn('{"say": "Resumo pronto."}');
        $this->instance(AiCliRunner::class, $runner);

        (new ProcessAssistantMessage($conversation, $this->user))->handle(app(AssistantChatService::class));

        $answer = $conversation->messages()->where('role', AssistantMessage::ROLE_ASSISTANT)->sole();
        $this->assertSame('Resumo pronto.', $answer->content);

        $attachment = AssistantMessageAttachment::sole();
        $this->assertSame(AssistantMessageAttachment::STATUS_PROCESSED, $attachment->status);
    }

    public function test_the_job_appends_a_failure_notice_when_an_attachment_fails_but_still_answers(): void
    {
        $conversation = $this->makeConversationWithAttachment();
        $message = $conversation->messages()->where('role', AssistantMessage::ROLE_USER)->sole();
        $attachment = $message->attachments()->sole();
        // Force the stored file to disappear so processing fails deterministically.
        Storage::disk('local')->delete($attachment->path);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn('{"say": "Não recebi nada para analisar."}');
        $this->instance(AiCliRunner::class, $runner);

        (new ProcessAssistantMessage($conversation, $this->user))->handle(app(AssistantChatService::class));

        $answer = $conversation->messages()->where('role', AssistantMessage::ROLE_ASSISTANT)->sole();
        $this->assertStringContainsString('Não recebi nada para analisar.', $answer->content);
        $this->assertStringContainsString('notas.txt', $answer->content);
        $this->assertStringContainsString('Não consegui processar', $answer->content);

        $attachment->refresh();
        $this->assertSame(AssistantMessageAttachment::STATUS_ERROR, $attachment->status);
    }

    /**
     * Simulates a job that dies without reaching handle()'s own try/catch
     * fallback (e.g. the worker itself is killed on timeout): failed()
     * must still close out the pending "Thinking..." state with an
     * assistant message.
     */
    public function test_failed_ends_the_pending_state_with_a_fallback_message(): void
    {
        $conversation = $this->makeConversationWithAttachment();

        (new ProcessAssistantMessage($conversation, $this->user))->failed(new RuntimeException('worker killed'));

        $answer = $conversation->messages()->where('role', AssistantMessage::ROLE_ASSISTANT)->sole();
        $this->assertNotEmpty($answer->content);
    }
}
