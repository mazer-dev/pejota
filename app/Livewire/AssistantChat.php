<?php

namespace App\Livewire;

use App\Jobs\ProcessAssistantMessage;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantMessageAttachment;
use App\Services\Ai\AssistantAttachmentUploader;
use App\Services\Ai\AssistantQuickAnswers;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class AssistantChat extends Component
{
    use WithFileUploads;

    public const DEFAULT_ATTACHMENTS_INSTRUCTION = 'Analise os anexos e apresente um resumo dos pontos principais.';

    public string $message = '';

    public ?int $conversationId = null;

    /**
     * Temporary Livewire uploads, held only until send() persists them as
     * definitive AssistantMessageAttachment files and clears this array.
     * Never dispatched to the queue directly.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $attachments = [];

    public string $attachmentError = '';

    public function updatedAttachments(): void
    {
        $this->attachmentError = '';
        $uploader = $this->attachmentUploader();

        if (! $uploader->isEnabled()) {
            $this->attachmentError = 'O envio de anexos está desabilitado no momento.';
            $this->attachments = [];

            return;
        }

        if (count($this->attachments) > $uploader->maxFiles()) {
            $this->attachmentError = "Você pode enviar no máximo {$uploader->maxFiles()} arquivos por mensagem.";
            $this->attachments = array_slice($this->attachments, 0, $uploader->maxFiles());
        }

        foreach ($this->attachments as $index => $file) {
            try {
                $uploader->assertValid($file);
            } catch (InvalidArgumentException $exception) {
                $this->attachmentError = $exception->getMessage();
                unset($this->attachments[$index]);
            }
        }

        $this->attachments = array_values($this->attachments);
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
        $this->attachmentError = '';
    }

    public function send(): void
    {
        $text = trim($this->message);
        $hasAttachments = $this->attachments !== [];

        if ($text === '' && ! $hasAttachments) {
            return;
        }

        $uploader = $this->attachmentUploader();

        try {
            $uploader->assertBatchIsValid($this->attachments);
        } catch (InvalidArgumentException $exception) {
            $this->attachmentError = $exception->getMessage();

            return;
        }

        $title = $text !== '' ? $text : $this->attachments[0]->getClientOriginalName();
        $conversation = $this->resolveConversation($title);

        $content = $text !== '' ? $text : self::DEFAULT_ATTACHMENTS_INSTRUCTION;

        $message = $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => $content,
        ]);

        foreach ($this->attachments as $file) {
            $uploader->persist($file, $message);
        }

        $conversation->touch();

        ProcessAssistantMessage::dispatch($conversation, auth()->user());

        $this->message = '';
        $this->attachments = [];
        $this->attachmentError = '';

        $this->dispatch('assistant-chat-scroll');
    }

    public function pollTick(): void
    {
        $this->dispatch('assistant-chat-scroll');
    }

    public function runChip(string $chip): void
    {
        $chips = AssistantQuickAnswers::chips();

        if (! array_key_exists($chip, $chips)) {
            return;
        }

        $answer = app(AssistantQuickAnswers::class)->answer($chip);

        if ($answer === null) {
            return;
        }

        $label = $chips[$chip];
        $conversation = $this->resolveConversation($label);

        $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => $label,
        ]);

        $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => $answer,
        ]);

        $conversation->touch();

        $this->dispatch('assistant-chat-scroll');
    }

    public function newConversation(): void
    {
        $this->conversationId = null;
        $this->attachments = [];
        $this->attachmentError = '';
        $this->message = '';
    }

    public function continueConversation(int $conversationId): void
    {
        $this->conversationId = AssistantConversation::find($conversationId)?->id;
        $this->attachments = [];
        $this->attachmentError = '';
    }

    private function resolveConversation(string $firstMessage): AssistantConversation
    {
        if ($this->conversationId) {
            $existing = AssistantConversation::find($this->conversationId);

            if ($existing) {
                return $existing;
            }
        }

        $conversation = AssistantConversation::create([
            'company_id' => auth()->user()->company->id,
            'user_id' => auth()->id(),
            'title' => Str::limit($firstMessage, 60),
        ]);

        $this->conversationId = $conversation->id;

        return $conversation;
    }

    private function attachmentUploader(): AssistantAttachmentUploader
    {
        return app(AssistantAttachmentUploader::class);
    }

    public function render(): View
    {
        $conversation = $this->conversationId
            ? AssistantConversation::with('messages.attachments')->find($this->conversationId)
            : null;

        if (! $conversation) {
            $this->conversationId = null;
        }

        $messages = $conversation?->messages ?? collect();
        $lastMessage = $messages->last();
        $pending = $lastMessage?->role === AssistantMessage::ROLE_USER;
        $pendingAttachments = $pending ? $lastMessage->attachments : collect();

        return view('livewire.assistant-chat', [
            'messages' => $messages,
            'pending' => $pending,
            'pendingProcessingAttachments' => $pending && $pendingAttachments->contains(
                fn (AssistantMessageAttachment $attachment): bool => in_array($attachment->status, [
                    AssistantMessageAttachment::STATUS_STORED,
                    AssistantMessageAttachment::STATUS_PROCESSING,
                ], true)
            ),
            'chips' => AssistantQuickAnswers::chips(),
            'attachmentsEnabled' => $this->attachmentUploader()->isEnabled(),
            'maxAttachmentFiles' => $this->attachmentUploader()->maxFiles(),
            'allowedAttachmentExtensions' => $this->attachmentUploader()->allowedExtensions(),
            'conversations' => AssistantConversation::query()
                ->latest('updated_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
