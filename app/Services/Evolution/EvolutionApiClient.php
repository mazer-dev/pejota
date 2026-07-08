<?php

namespace App\Services\Evolution;

use App\Models\WhatsappConversation;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EvolutionApiClient
{
    public function sendText(WhatsappConversation $conversation, string $text): array
    {
        $number = $conversation->phone_number ?: $this->numberFromJid($conversation->remote_jid);
        if ($number === null) {
            throw new RuntimeException('Não foi possível identificar o número do WhatsApp desta conversa.');
        }

        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders(['apikey' => $this->apiKey()])
                ->post($this->endpoint('/message/sendText/'.$this->instance()), [
                    'number' => $number,
                    'text' => $text,
                    'delay' => 1000,
                    'linkPreview' => false,
                ]);

            $response->throw();

            return $response->json() ?? [];
        } catch (RequestException $exception) {
            $message = $exception->response?->body() ?: $exception->getMessage();

            throw new RuntimeException("Falha ao enviar mensagem pela Evolution API: {$message}", previous: $exception);
        }
    }

    public function setWebhook(string $url, bool $base64 = true): array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders(['apikey' => $this->apiKey()])
                ->post($this->endpoint('/webhook/set/'.$this->instance()), [
                    'webhook' => [
                        'enabled' => true,
                        'url' => $url,
                        'webhookByEvents' => false,
                        'webhookBase64' => $base64,
                        'events' => [
                            'MESSAGES_UPSERT',
                            'MESSAGES_UPDATE',
                            'SEND_MESSAGE',
                        ],
                    ],
                ]);

            $response->throw();

            return $response->json() ?? [];
        } catch (RequestException $exception) {
            $message = $exception->response?->body() ?: $exception->getMessage();

            throw new RuntimeException("Falha ao configurar webhook da Evolution API: {$message}", previous: $exception);
        }
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.evolution.base_url'), '/').$path;
    }

    private function apiKey(): string
    {
        $apiKey = config('services.evolution.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('EVOLUTION_API_KEY não configurada.');
        }

        return $apiKey;
    }

    private function instance(): string
    {
        $instance = config('services.evolution.instance');
        if (! is_string($instance) || trim($instance) === '') {
            throw new RuntimeException('EVOLUTION_INSTANCE não configurada.');
        }

        return $instance;
    }

    private function timeout(): int
    {
        return (int) config('services.evolution.timeout', 30);
    }

    private function numberFromJid(?string $jid): ?string
    {
        if (! $jid || str_contains($jid, '@lid')) {
            return null;
        }

        $number = preg_replace('/\D+/', '', str($jid)->before('@')->toString());

        return $number === '' ? null : $number;
    }
}
