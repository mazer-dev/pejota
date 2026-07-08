<?php

namespace App\Services\Ai;

use App\Models\WhatsappConversation;
use App\Services\Ai\Context\ClientContextBuilder;
use App\Services\Ai\Context\PromptGuard;

class CliWhatsappMessageSuggester
{
    public function __construct(
        private readonly ClientContextBuilder $contextBuilder,
        private readonly AiCliRunner $cliRunner,
    ) {}

    public function suggest(WhatsappConversation $conversation, ?string $draft = null, ?string $instruction = null): string
    {
        $context = $this->contextBuilder->forSuggestion($conversation);

        return trim($this->cliRunner->complete($this->prompt($context, $draft, $instruction)));
    }

    private function prompt(string $context, ?string $draft, ?string $instruction = null): string
    {
        $parts = [
            implode("\n", [
                'Você escreve respostas de WhatsApp para o Luiz/Pejota.',
                'Responda em português do Brasil, com tom profissional, natural, direto e humano.',
                'Use apenas informações do contexto e da conversa. Não invente preço, prazo, escopo, promessa ou decisão.',
                'Considere as tarefas e faturas em aberto do cliente para sugerir próximos passos concretos, quando fizer sentido na conversa.',
                'Imite o tom e o estilo das mensagens de exemplo do Luiz, quando disponíveis no contexto.',
                'Se algo depender de confirmação, pergunte de forma objetiva.',
                PromptGuard::instruction(),
                'Retorne somente o texto da mensagem, sem aspas, títulos, bullets de análise ou explicações.',
            ]),
            "Contexto disponível:\n".PromptGuard::wrap($context),
        ];

        if (filled($draft)) {
            $parts[] = "Rascunho atual do Luiz, se útil para melhorar/completar:\n".PromptGuard::wrap(trim((string) $draft));
        }

        if (filled($instruction)) {
            $parts[] = "O Luiz descreveu o que ele quer comunicar ao cliente (instrução dele para você; não é a mensagem pronta, nem precisa ser uma resposta à última mensagem da conversa):\n"
                .trim((string) $instruction);
            $parts[] = 'Escreva a mensagem que o Luiz deve enviar no WhatsApp cumprindo essa instrução, encaixada no contexto do cliente e do projeto.';
        } else {
            $parts[] = 'Escreva a próxima mensagem que o Luiz deve enviar no WhatsApp.';
        }

        return implode("\n\n", $parts);
    }
}
