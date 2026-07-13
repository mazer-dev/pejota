<?php

namespace App\Jobs;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantMessageAttachment;
use App\Models\User;
use App\Services\Ai\AssistantAttachmentProcessor;
use App\Services\Ai\AssistantChatService;
use App\Services\Ai\OpenAiAudioTranscriber;
use App\Services\Assistant\WhatsappMarkdownConverter;
use App\Services\Evolution\EvolutionApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * WhatsApp mirror of ProcessAssistantMessage. Dispatched with a debounce
 * delay: when a burst of messages arrives, every message dispatches a job,
 * but only the job whose message is still the newest actually answers —
 * older jobs stop at the guard (after transcribing their own audio, so the
 * newest job's prompt sees the transcription).
 */
class ProcessAssistantWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_CHARS = 4000;

    public int $timeout = 420;

    public int $tries = 1;

    public function __construct(
        public readonly AssistantConversation $conversation,
        public readonly User $user,
        public readonly AssistantMessage $userMessage,
        public readonly ?string $pendingAudioPath = null,
    ) {
        if ($this->userMessage->attachments()->exists()) {
            $this->timeout = max($this->timeout, (int) config('services.assistant.attachments.timeout', 900));
        }
    }

    public function handle(AssistantChatService $service): void
    {
        Auth::onceUsingId($this->user->id);

        if (! $this->transcribePendingAudio()) {
            return;
        }

        $conversation = $this->conversation->fresh();

        if ($conversation === null || $conversation->closed_at !== null) {
            return;
        }

        $hasNewerUserMessage = $conversation->messages()
            ->where('role', AssistantMessage::ROLE_USER)
            ->where('id', '>', $this->userMessage->id)
            ->exists();

        if ($hasNewerUserMessage) {
            return;
        }

        $failures = $this->processPendingAttachments($conversation);

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

        $this->sendWhatsappAnswer($answer);
    }

    /**
     * Transcribes this job's own audio BEFORE the burst guard, updating the
     * message content in place — so even when a newer message's job ends up
     * answering, the prompt already carries this audio's text. Returns
     * false when the job must stop (transcription failed and the user was
     * notified).
     */
    private function transcribePendingAudio(): bool
    {
        if ($this->pendingAudioPath === null) {
            return true;
        }

        $disk = Storage::disk('local');

        try {
            $text = app(OpenAiAudioTranscriber::class)->transcribe($disk->path($this->pendingAudioPath));

            $this->userMessage->forceFill(['content' => $text])->save();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            $this->userMessage->forceFill([
                'content' => '[Áudio recebido, mas a transcrição falhou.]',
            ])->save();

            $this->trySendText('⚠️ Não consegui transcrever seu áudio. Pode escrever a pergunta em texto?');

            return false;
        } finally {
            if ($disk->exists($this->pendingAudioPath)) {
                $disk->delete($this->pendingAudioPath);
            }
        }
    }

    /**
     * Processes every still-unprocessed attachment in the conversation, not
     * only this message's: in a burst, earlier jobs stop at the guard, so
     * the answering job inherits their pending attachments.
     *
     * @return array<int, array{attachment: AssistantMessageAttachment, error: string}>
     */
    private function processPendingAttachments(AssistantConversation $conversation): array
    {
        $pending = AssistantMessageAttachment::allTenants()
            ->where('company_id', $conversation->company_id)
            ->whereIn('assistant_message_id', $conversation->messages()->pluck('id'))
            ->where('status', AssistantMessageAttachment::STATUS_STORED)
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            return [];
        }

        return app(AssistantAttachmentProcessor::class)->processAll($pending);
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

    private function sendWhatsappAnswer(string $markdown): void
    {
        $converter = app(WhatsappMarkdownConverter::class);
        $text = $converter->toWhatsapp($markdown);

        foreach ($converter->chunk($text, self::CHUNK_CHARS) as $chunk) {
            $this->trySendText($chunk, reportFailure: true);
        }
    }

    private function trySendText(string $text, bool $reportFailure = false): void
    {
        $number = (string) $this->conversation->whatsapp_number;

        if ($number === '') {
            return;
        }

        try {
            app(EvolutionApiClient::class)->sendTextToNumber(
                (string) config('services.assistant.whatsapp.instance'),
                $number,
                $text,
            );
        } catch (Throwable $exception) {
            if ($reportFailure) {
                report($exception);
            }
        }
    }

    /**
     * Runs when the job dies without reaching handle()'s own fallback (e.g.
     * worker timeout): closes the pending state in the conversation AND
     * warns the user on WhatsApp, so the silence never looks like the
     * assistant is still thinking.
     */
    public function failed(?Throwable $exception): void
    {
        $this->conversation->messages()->create([
            'company_id' => $this->conversation->company_id,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => __('The assistant failed to answer. Please try again.'),
        ]);

        $this->conversation->touch();

        $this->trySendText('⚠️ O assistente não conseguiu responder. Tente novamente.');
    }
}
