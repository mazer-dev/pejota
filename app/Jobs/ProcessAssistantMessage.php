<?php

namespace App\Jobs;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantMessageAttachment;
use App\Models\User;
use App\Services\Ai\AssistantAttachmentProcessor;
use App\Services\Ai\AssistantChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ProcessAssistantMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The agentic loop shells out to AI CLIs several times, easily exceeding
     * the worker's default 60s timeout (which killed the job AND the worker,
     * leaving the chat stuck on "Thinking...").
     */
    public int $timeout = 420;

    public int $tries = 1;

    public function __construct(
        public readonly AssistantConversation $conversation,
        public readonly User $user,
    ) {
        // Attachments route through AGY/Codex sequentially (one CPU on the
        // VPS) before the chat service even runs, so they get the longer,
        // dedicated attachments timeout instead of the plain-text default.
        if ($this->lastUserMessage()?->attachments()->exists()) {
            $this->timeout = max($this->timeout, (int) config('services.assistant.attachments.timeout', 900));
        }
    }

    /**
     * Only takes AssistantChatService as a real parameter (kept
     * backward-compatible with call sites that invoke handle() directly in
     * tests); AssistantAttachmentProcessor is resolved internally instead
     * of via method injection for the same reason.
     */
    public function handle(AssistantChatService $service): void
    {
        // Queue workers run without an authenticated user; log the
        // requesting user in for this job only (PejotaHelper and friends
        // read settings from auth()->user()).
        Auth::onceUsingId($this->user->id);

        $conversation = $this->conversation->fresh();
        $failures = [];

        $lastUserMessage = $this->lastUserMessage();
        if ($lastUserMessage) {
            $attachments = $lastUserMessage->attachments()->get();

            if ($attachments->isNotEmpty()) {
                $failures = app(AssistantAttachmentProcessor::class)->processAll($attachments);
            }
        }

        try {
            $answer = $service->respond($conversation);
        } catch (Throwable $exception) {
            report($exception);

            $answer = __('The assistant failed to answer. Please try again.');
        }

        if ($failures !== []) {
            $answer = rtrim($answer)."\n\n".$this->failuresNotice($failures);
        }

        $this->conversation->messages()->create([
            'company_id' => $this->conversation->company_id,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => $answer,
        ]);

        $this->conversation->touch();
    }

    private function lastUserMessage(): ?AssistantMessage
    {
        // See the identical comment in AssistantChatService::lastUserMessage():
        // the messages() relation already orders oldest('id') first, so a
        // plain latest('id') here would be a no-op appended after it and
        // this would silently return the OLDEST user message instead.
        return $this->conversation->messages()
            ->where('role', AssistantMessage::ROLE_USER)
            ->reorder('id', 'desc')
            ->first();
    }

    /**
     * @param  array<int, array{attachment: AssistantMessageAttachment, error: string}>  $failures
     */
    private function failuresNotice(array $failures): string
    {
        $lines = array_map(
            fn (array $failure): string => '- '.($failure['attachment']->original_filename ?? 'arquivo').': '.$failure['error'],
            $failures,
        );

        return '⚠️ Não consegui processar '.count($failures)." anexo(s):\n".implode("\n", $lines);
    }

    /**
     * Runs when the job dies without reaching handle()'s own fallback (e.g.
     * worker timeout), so the UI never hangs on "Thinking..." forever.
     */
    public function failed(?Throwable $exception): void
    {
        $this->conversation->messages()->create([
            'company_id' => $this->conversation->company_id,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => __('The assistant failed to answer. Please try again.'),
        ]);

        $this->conversation->touch();
    }
}
