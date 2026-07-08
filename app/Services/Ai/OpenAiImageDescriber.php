<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiImageDescriber
{
    public function describe(string $filePath, ?string $mimeType = null): string
    {
        $realPath = realpath($filePath);
        if ($realPath === false || ! is_file($realPath)) {
            throw new RuntimeException("Imagem não encontrada: {$filePath}");
        }

        $mimeType = $mimeType ?: mime_content_type($realPath) ?: 'image/jpeg';
        if (! str_starts_with($mimeType, 'image/')) {
            throw new RuntimeException("Arquivo não é uma imagem compatível: {$mimeType}");
        }

        $maxBytes = (int) config('services.openai.image_max_mb', 10) * 1024 * 1024;
        if (filesize($realPath) > $maxBytes) {
            throw new RuntimeException('Imagem excede o limite configurado para descrição por IA.');
        }

        $base64 = base64_encode((string) file_get_contents($realPath));

        try {
            $response = Http::timeout((int) config('services.openai.timeout', 120))
                ->withToken($this->apiKey())
                ->post($this->endpoint('/chat/completions'), [
                    'model' => config('services.openai.image_model', config('services.openai.chat_model', 'gpt-4o-mini')),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => implode("\n", [
                                'Você descreve imagens recebidas pelo WhatsApp para contexto de atendimento comercial e técnico.',
                                'Responda em português do Brasil, de forma objetiva.',
                                'Inclua textos visíveis, valores, datas, telas, erros, pessoas, objetos e qualquer detalhe útil para responder ao cliente.',
                                'Não invente informações que não aparecem na imagem.',
                            ]),
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Descreva esta imagem para que outra IA consiga responder ao cliente usando esse contexto.',
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$base64}",
                                        'detail' => 'auto',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

            $response->throw();

            $text = data_get($response->json(), 'choices.0.message.content');
            if (! is_string($text) || trim($text) === '') {
                throw new RuntimeException('A OpenAI não retornou uma descrição da imagem.');
            }

            return trim($text);
        } catch (RequestException $exception) {
            $message = $exception->response?->json('error.message')
                ?: $exception->response?->body()
                ?: $exception->getMessage();

            throw new RuntimeException("Falha ao descrever imagem pela OpenAI: {$message}", previous: $exception);
        }
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
