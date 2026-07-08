<?php

namespace App\Services\Ai\Context;

use App\Models\WhatsappAttachment;
use App\Models\WhatsappMessage;
use Illuminate\Support\Collection;

/**
 * Renders a WhatsApp conversation's messages (and their attachments) into a
 * single, plain-text transcript, one line per message, oldest first.
 *
 * This is the single source of truth for that rendering: both
 * CliWhatsappMessageSuggester (context sent to the AI CLI) and
 * WhatsappConversationTokenService (token accounting for that same context)
 * must use it so the token count reflects what is actually sent.
 */
class ConversationHistoryRenderer
{
    /**
     * @param  Collection<int, WhatsappMessage>  $messages
     * @param  string  $fromMeLabel  Label used for messages sent by us (from_me = true).
     * @param  int|null  $limit  Keep only the N most recent messages. Null keeps the full history.
     */
    public function render(Collection $messages, string $fromMeLabel = 'Luiz', ?int $limit = null): string
    {
        $sorted = $messages
            ->sortBy(fn (WhatsappMessage $message): string => ($message->sent_at?->format('Y-m-d H:i:s') ?? '')
                .str_pad((string) $message->id, 12, '0', STR_PAD_LEFT))
            ->values();

        if ($limit !== null) {
            $sorted = $sorted->take(-$limit);
        }

        return $sorted
            ->map(fn (WhatsappMessage $message): string => $this->messageLine($message, $fromMeLabel))
            ->filter()
            ->implode("\n");
    }

    private function messageLine(WhatsappMessage $message, string $fromMeLabel): string
    {
        $parts = [
            $message->sent_at?->format('Y-m-d H:i'),
            $message->from_me ? $fromMeLabel : ($message->sender_name ?: 'Cliente'),
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

        return $text === '' ? null : "Anexos:\n{$text}";
    }

    private function attachmentContext(WhatsappAttachment $attachment): ?string
    {
        $label = collect([
            $attachment->original_filename,
            $attachment->mime_type,
        ])->filter()->implode(' - ');

        $prefix = $label !== '' ? "Anexo ({$label})" : 'Anexo';

        if (filled($attachment->transcription_text)) {
            return "{$prefix} - transcrição de áudio:\n{$attachment->transcription_text}";
        }

        if (filled($attachment->extracted_text)) {
            return "{$prefix} - conteúdo processado:\n{$attachment->extracted_text}";
        }

        if (filled($attachment->mime_type) || filled($attachment->original_filename)) {
            return "{$prefix} - sem texto extraído salvo.";
        }

        return null;
    }
}
