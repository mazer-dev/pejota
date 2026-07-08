<?php

namespace App\Services\Ai;

use App\Models\WhatsappAttachment;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiWhatsappMessageSuggester
{
    public function __construct(
        private readonly ConversationContextBuilder $contextBuilder,
    ) {}

    public function suggest(WhatsappConversation $conversation, ?string $draft = null): string
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

        try {
            $response = Http::timeout((int) config('services.openai.timeout', 120))
                ->withToken($this->apiKey())
                ->post($this->endpoint('/chat/completions'), [
                    'model' => config('services.openai.chat_model', 'gpt-4o-mini'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => implode("\n", [
                                'Você escreve respostas de WhatsApp para o Luiz/Pejota.',
                                'Responda em português do Brasil, com tom profissional, natural, direto e humano.',
                                'Use apenas informações do contexto e da conversa. Não invente preço, prazo, escopo, promessa ou decisão.',
                                'Se algo depender de confirmação, pergunte de forma objetiva.',
                                'Retorne somente o texto da mensagem, sem aspas, títulos, bullets de análise ou explicações.',
                            ]),
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->userPrompt($context, $draft),
                        ],
                    ],
                ]);

            $response->throw();

            $text = data_get($response->json(), 'choices.0.message.content');
            if (! is_string($text) || trim($text) === '') {
                throw new RuntimeException('A OpenAI não retornou uma sugestão de mensagem.');
            }

            return trim($text);
        } catch (RequestException $exception) {
            $message = $exception->response?->json('error.message')
                ?: $exception->response?->body()
                ?: $exception->getMessage();

            throw new RuntimeException("Falha ao gerar sugestão pela OpenAI: {$message}", previous: $exception);
        }
    }

    private function userPrompt(string $context, ?string $draft): string
    {
        $parts = [
            "Contexto disponível:\n".$context,
        ];

        if (filled($draft)) {
            $parts[] = "Rascunho atual do Luiz, se útil para melhorar/completar:\n".trim((string) $draft);
        }

        $parts[] = 'Escreva a próxima mensagem que o Luiz deve enviar no WhatsApp.';

        return implode("\n\n", $parts);
    }

    private function conversationHistory(WhatsappConversation $conversation): string
    {
        return $conversation->messages
            ->sortBy(fn (WhatsappMessage $message): string => ($message->sent_at?->format('Y-m-d H:i:s') ?? '').str_pad((string) $message->id, 12, '0', STR_PAD_LEFT))
            ->take(-30)
            ->map(fn (WhatsappMessage $message): string => $this->messageLine($message))
            ->filter()
            ->implode("\n");
    }

    private function messageLine(WhatsappMessage $message): string
    {
        $parts = [
            $message->sent_at?->format('Y-m-d H:i'),
            $message->from_me ? 'Luiz' : ($message->sender_name ?: 'Cliente'),
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

    private function apiKey(): string
    {
        $apiKey = config('services.openai.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('OPENAI_API_KEY não configurada.');
        }

        return $apiKey;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').$path;
    }
}
