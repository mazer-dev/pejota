<?php

namespace App\Services\Evolution;

use App\Models\WhatsappConversation;
use App\Services\Ai\Context\ClientContextBuilder;
use App\Services\Ai\OpenAiTokenCounter;

class WhatsappConversationTokenService
{
    public function __construct(
        private readonly ClientContextBuilder $contextBuilder,
        private readonly OpenAiTokenCounter $tokenCounter,
    ) {}

    /**
     * Counts tokens for the exact same context CliWhatsappMessageSuggester
     * sends to the AI CLI (ClientContextBuilder::forSuggestion()), so the
     * token count shown to the user reflects what is actually sent.
     */
    public function refresh(WhatsappConversation $conversation): int
    {
        $context = $this->contextBuilder->forSuggestion($conversation);

        $tokens = $this->tokenCounter->count($context);

        $conversation->forceFill([
            'context_tokens' => $tokens,
            'context_updated_at' => now(),
        ])->save();

        return $tokens;
    }
}
