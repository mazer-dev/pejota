<?php

namespace App\Services\Evolution;

use App\Models\WhatsappAttachment;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ai\ConversationContextBuilder;
use App\Services\Ai\OpenAiTokenCounter;

class WhatsappConversationTokenService
{
    public function __construct(
        private readonly ConversationContextBuilder $contextBuilder,
        private readonly OpenAiTokenCounter $tokenCounter,
    ) {}

    public function refresh(WhatsappConversation $conversation): int
    {
        $conversation->loadMissing([
            'client',
            'project',
            'messages.attachments',
        ]);

        $context = $this->contextBuilder->build(
            client: $conversation->client,
            project: $conversation->project,
            conversationContext: $this->conversationHistory($conversation),
        );

        $tokens = $this->tokenCounter->count($context);

        $conversation->forceFill([
            'context_tokens' => $tokens,
            'context_updated_at' => now(),
        ])->save();

        return $tokens;
    }

    private function conversationHistory(WhatsappConversation $conversation): string
    {
        return $conversation->messages
            ->map(fn (WhatsappMessage $message): string => $this->messageLine($message))
            ->filter()
            ->implode("\n");
    }

    private function messageLine(WhatsappMessage $message): string
    {
        $parts = [
            $message->sent_at?->format('Y-m-d H:i'),
            $message->from_me ? 'Pejota' : ($message->sender_name ?: 'Cliente'),
            $message->text,
            $this->attachmentText($message),
        ];

        return collect($parts)
            ->filter(fn ($part): bool => is_string($part) && trim($part) !== '')
            ->implode(' | ');
    }

    private function attachmentText(WhatsappMessage $message): ?string
    {
        $text = $message->attachments
            ->map(fn (WhatsappAttachment $attachment): ?string => $this->attachmentContext($attachment))
            ->filter()
            ->implode("\n");

        return $text === '' ? null : $text;
    }

    private function attachmentContext(WhatsappAttachment $attachment): ?string
    {
        if (filled($attachment->transcription_text)) {
            return 'Transcrição de áudio: '.$attachment->transcription_text;
        }

        if (filled($attachment->extracted_text)) {
            return 'Conteúdo processado de anexo: '.$attachment->extracted_text;
        }

        $label = collect([
            $attachment->original_filename,
            $attachment->mime_type,
        ])->filter()->implode(' - ');

        return $label !== '' ? "Anexo sem texto extraído: {$label}" : null;
    }
}
