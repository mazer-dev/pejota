<?php

namespace App\Services\Evolution;

use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

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
                ->post($this->endpoint('/message/sendText/'.$this->instance($conversation->evolution_instance)), [
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

    public function sendMedia(
        WhatsappConversation $conversation,
        string $base64,
        string $mimeType,
        string $fileName,
        ?string $caption = null,
    ): array {
        $number = $conversation->phone_number ?: $this->numberFromJid($conversation->remote_jid);
        if ($number === null) {
            throw new RuntimeException('Não foi possível identificar o número do WhatsApp desta conversa.');
        }

        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders(['apikey' => $this->apiKey()])
                ->post($this->endpoint('/message/sendMedia/'.$this->instance($conversation->evolution_instance)), array_filter([
                    'number' => $number,
                    'mediatype' => $this->mediaTypeFromMime($mimeType),
                    'mimetype' => $mimeType,
                    'caption' => filled($caption) ? trim((string) $caption) : null,
                    'media' => $base64,
                    'fileName' => $fileName,
                    'delay' => 1000,
                ], fn ($value): bool => $value !== null));

            $response->throw();

            return $response->json() ?? [];
        } catch (RequestException $exception) {
            $message = $exception->response?->body() ?: $exception->getMessage();

            throw new RuntimeException("Falha ao enviar anexo pela Evolution API: {$message}", previous: $exception);
        }
    }

    public function updateMessage(WhatsappConversation $conversation, WhatsappMessage $message, string $text): array
    {
        $number = $conversation->phone_number ?: $this->numberFromJid($conversation->remote_jid);
        if ($number === null) {
            throw new RuntimeException('Não foi possível identificar o número do WhatsApp desta conversa.');
        }

        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders(['apikey' => $this->apiKey()])
                ->post($this->endpoint('/chat/updateMessage/'.$this->instance($conversation->evolution_instance)), [
                    'number' => $number,
                    'key' => [
                        'remoteJid' => $message->remote_jid,
                        'fromMe' => (bool) $message->from_me,
                        'id' => $message->remote_message_id,
                    ],
                    'text' => $text,
                ]);

            $response->throw();

            return $response->json() ?? [];
        } catch (RequestException $exception) {
            $message = $exception->response?->body() ?: $exception->getMessage();

            throw new RuntimeException("Falha ao editar mensagem pela Evolution API: {$message}", previous: $exception);
        }
    }

    public function deleteMessageForEveryone(WhatsappConversation $conversation, WhatsappMessage $message): array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders(['apikey' => $this->apiKey()])
                ->delete($this->endpoint('/chat/deleteMessageForEveryone/'.$this->instance($conversation->evolution_instance)), [
                    'id' => $message->remote_message_id,
                    'remoteJid' => $message->remote_jid,
                    'fromMe' => (bool) $message->from_me,
                ]);

            $response->throw();

            return $response->json() ?? [];
        } catch (RequestException $exception) {
            $message = $exception->response?->body() ?: $exception->getMessage();

            throw new RuntimeException("Falha ao excluir mensagem pela Evolution API: {$message}", previous: $exception);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchInstances(): array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders(['apikey' => $this->apiKey()])
                ->get($this->endpoint('/instance/fetchInstances'));

            $response->throw();

            $instances = $response->json();

            return is_array($instances) ? $instances : [];
        } catch (RequestException $exception) {
            $message = $exception->response?->body() ?: $exception->getMessage();

            throw new RuntimeException("Falha ao listar instâncias da Evolution API: {$message}", previous: $exception);
        }
    }

    /**
     * @return array<string, string>
     */
    public function instanceOptions(): array
    {
        $options = [];

        try {
            foreach ($this->fetchInstances() as $instance) {
                $name = data_get($instance, 'name')
                    ?: data_get($instance, 'instanceName')
                    ?: data_get($instance, 'instance.instanceName');

                if (! is_string($name) || trim($name) === '') {
                    continue;
                }

                $status = data_get($instance, 'connectionStatus')
                    ?: data_get($instance, 'state')
                    ?: data_get($instance, 'instance.state');

                $profile = data_get($instance, 'profileName');
                $details = collect([$profile, $status])
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->implode(' - ');

                $options[$name] = $details === '' ? $name : "{$name} ({$details})";
            }
        } catch (Throwable) {
            $options = [];
        }

        $configured = config('services.evolution.instance');
        if (is_string($configured) && trim($configured) !== '' && ! array_key_exists($configured, $options)) {
            $options[$configured] = $configured;
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMessages(string $instance, string $remoteJid, int $limit = 50): array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders(['apikey' => $this->apiKey()])
                ->post($this->endpoint('/chat/findMessages/'.$this->instance($instance)), [
                    'where' => [
                        'key' => [
                            'remoteJid' => $remoteJid,
                        ],
                    ],
                    'limit' => $limit,
                ]);

            $response->throw();

            $records = data_get($response->json(), 'messages.records');

            return is_array($records) ? array_filter($records, 'is_array') : [];
        } catch (RequestException $exception) {
            $message = $exception->response?->body() ?: $exception->getMessage();

            throw new RuntimeException("Falha ao buscar mensagens da Evolution API: {$message}", previous: $exception);
        }
    }

    /**
     * @return array{mime_type: ?string, data: string}|null
     */
    public function getBase64FromMediaMessage(string $instance, array $messageData): ?array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders(['apikey' => $this->apiKey()])
                ->post($this->endpoint('/chat/getBase64FromMediaMessage/'.$this->instance($instance)), [
                    'message' => $messageData,
                    'convertToMp4' => false,
                ]);

            $response->throw();

            $data = $response->json();
            $raw = data_get($data, 'base64')
                ?: data_get($data, 'data.base64')
                ?: data_get($data, 'data')
                ?: data_get($data, 'media');

            if (! is_string($raw) || trim($raw) === '') {
                return null;
            }

            $mimeType = data_get($data, 'mimetype')
                ?: data_get($data, 'mimeType')
                ?: data_get($data, 'data.mimetype')
                ?: data_get($data, 'data.mimeType');

            if (preg_match('/^data:(?<mime>[^;]+);base64,(?<data>.+)$/s', trim($raw), $matches)) {
                return [
                    'mime_type' => $matches['mime'] ?: (is_string($mimeType) ? $mimeType : null),
                    'data' => trim($matches['data']),
                ];
            }

            return [
                'mime_type' => is_string($mimeType) ? $mimeType : null,
                'data' => trim($raw),
            ];
        } catch (RequestException $exception) {
            $message = $exception->response?->body() ?: $exception->getMessage();

            throw new RuntimeException("Falha ao baixar mídia da Evolution API: {$message}", previous: $exception);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findChats(string $instance): array
    {
        return $this->findChatRecords('/chat/findChats/'.$this->instance($instance), 'chats');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findContacts(string $instance): array
    {
        return $this->findChatRecords('/chat/findContacts/'.$this->instance($instance), 'contacts');
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
                        'byEvents' => false,
                        'base64' => $base64,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findChatRecords(string $path, string $collectionKey): array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders(['apikey' => $this->apiKey()])
                ->post($this->endpoint($path), [
                    'where' => (object) [],
                ]);

            $response->throw();

            $data = $response->json();
            $records = is_array($data) && array_is_list($data)
                ? $data
                : data_get($data, $collectionKey.'.records', data_get($data, $collectionKey, data_get($data, 'records', [])));

            return is_array($records) ? array_filter($records, 'is_array') : [];
        } catch (RequestException $exception) {
            $message = $exception->response?->body() ?: $exception->getMessage();

            throw new RuntimeException("Falha ao buscar registros de chat da Evolution API: {$message}", previous: $exception);
        }
    }

    private function apiKey(): string
    {
        $apiKey = config('services.evolution.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('EVOLUTION_API_KEY não configurada.');
        }

        return $apiKey;
    }

    private function instance(?string $instanceName = null): string
    {
        $instance = $instanceName ?: config('services.evolution.instance');
        if (! is_string($instance) || trim($instance) === '') {
            throw new RuntimeException('EVOLUTION_INSTANCE não configurada.');
        }

        return $instance;
    }

    private function timeout(): int
    {
        return (int) config('services.evolution.timeout', 30);
    }

    private function mediaTypeFromMime(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };
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
