<?php

namespace App\Services\Ai\Context;

use App\Models\Client;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;

/**
 * Samples up to 15 of the most recent WhatsApp messages sent by us
 * (from_me = true), used as a writing-style reference. When a client is
 * given, messages are pulled across all of that client's conversations,
 * not just the current one.
 */
class LuizStyleContextSection
{
    private const SAMPLE_SIZE = 15;

    public function __construct(
        private readonly ConversationHistoryRenderer $historyRenderer,
    ) {}

    public function build(?Client $client = null, ?WhatsappConversation $conversation = null): ?string
    {
        $query = WhatsappMessage::query()
            ->with('attachments')
            ->where('from_me', true)
            ->whereNotNull('text')
            ->where('text', '!=', '');

        if ($conversation) {
            $query->where('whatsapp_conversation_id', $conversation->id);
        } elseif ($client) {
            $query->where('client_id', $client->id);
        } else {
            return null;
        }

        $messages = $query
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(self::SAMPLE_SIZE)
            ->get();

        if ($messages->isEmpty()) {
            return null;
        }

        $rendered = $this->historyRenderer->render($messages, 'Luiz');

        return "Estilo de escrita do Luiz (amostra de mensagens recentes, para imitar o tom):\n{$rendered}";
    }
}
