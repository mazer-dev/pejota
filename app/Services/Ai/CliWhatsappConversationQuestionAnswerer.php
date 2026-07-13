<?php

namespace App\Services\Ai;

use App\Models\WhatsappConversation;
use App\Services\Ai\Context\ClientContextBuilder;
use App\Services\Ai\Context\PromptGuard;

class CliWhatsappConversationQuestionAnswerer
{
    public function __construct(
        private readonly ClientContextBuilder $contextBuilder,
        private readonly AiCliRunner $cliRunner,
    ) {}

    public function answer(WhatsappConversation $conversation, string $question): string
    {
        $context = $this->contextBuilder->forSuggestion($conversation);

        $prompt = implode("\n\n", [
            implode("\n", [
                'Você responde perguntas factuais do Luiz sobre uma conversa de WhatsApp, a pessoa nela, o cliente e o projeto vinculados.',
                'Use somente fatos presentes no contexto fornecido.',
                'Não invente, não presuma e não complete lacunas com conhecimento externo.',
                'Quando a informação pedida não estiver disponível, diga claramente que ela não foi encontrada no histórico ou nos dados vinculados.',
                'Responda em português do Brasil, de forma direta e suficiente.',
                PromptGuard::instruction(),
            ]),
            "Contexto disponível:\n".PromptGuard::wrap($context),
            "Pergunta do Luiz:\n".PromptGuard::wrap(trim($question)),
            'Responda à pergunta agora.',
        ]);

        return trim($this->cliRunner->complete($prompt));
    }
}
