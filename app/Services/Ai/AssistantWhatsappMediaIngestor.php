<?php

namespace App\Services\Ai;

use App\Models\AssistantMessage;
use App\Services\Evolution\EvolutionApiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Turns WhatsApp webhook media into assistant inputs. Audio is saved as a
 * TEMPORARY file (assistant/{company}/{conversation}/tmp/{uuid}.{ext} on
 * the local disk) for the queue job to transcribe and delete — it never
 * becomes an AssistantMessageAttachment. Images and documents go through
 * the exact same validation + persistence path as browser uploads
 * (AssistantAttachmentUploader::persistFromPath). Video/sticker are
 * unsupported. Bytes come from the webhook's inline base64 when present,
 * falling back to Evolution's getBase64FromMediaMessage endpoint.
 */
class AssistantWhatsappMediaIngestor
{
    public const KIND_NONE = 'none';

    public const KIND_AUDIO = 'audio';

    public const KIND_ATTACHMENT = 'attachment';

    public const KIND_UNSUPPORTED = 'unsupported';

    private const ATTACHMENT_MEDIA_KEYS = ['imageMessage', 'documentMessage'];

    private const UNSUPPORTED_MEDIA_KEYS = ['videoMessage', 'stickerMessage', 'ptvMessage'];

    public function __construct(
        private readonly EvolutionApiClient $client,
        private readonly AssistantAttachmentUploader $uploader,
    ) {}

    public function kind(array $messageData): string
    {
        $message = data_get($messageData, 'message', []);

        if (! is_array($message)) {
            return self::KIND_NONE;
        }

        if (is_array($message['audioMessage'] ?? null)) {
            return self::KIND_AUDIO;
        }

        foreach (self::ATTACHMENT_MEDIA_KEYS as $key) {
            if (is_array($message[$key] ?? null)) {
                return self::KIND_ATTACHMENT;
            }
        }

        foreach (self::UNSUPPORTED_MEDIA_KEYS as $key) {
            if (is_array($message[$key] ?? null)) {
                return self::KIND_UNSUPPORTED;
            }
        }

        return self::KIND_NONE;
    }

    public function filename(array $messageData): ?string
    {
        $message = data_get($messageData, 'message', []);

        if (! is_array($message)) {
            return null;
        }

        $filename = data_get($message, 'documentMessage.fileName');
        if (is_string($filename) && trim($filename) !== '') {
            return trim($filename);
        }

        foreach (['imageMessage', 'audioMessage', ...self::UNSUPPORTED_MEDIA_KEYS] as $key) {
            $media = data_get($message, $key);
            if (is_array($media)) {
                $extension = $this->extensionFromMime((string) data_get($media, 'mimetype')) ?? 'bin';
                $label = str_replace('Message', '', $key);

                return "{$label}-".Str::uuid()->toString().".{$extension}";
            }
        }

        return null;
    }

    /**
     * Ingests media for an already-created assistant message.
     *
     * @return array{kind: string, audio_path: ?string, error: ?string}
     */
    public function ingest(array $payload, array $messageData, AssistantMessage $message): array
    {
        $kind = $this->kind($messageData);

        if ($kind === self::KIND_NONE) {
            return ['kind' => $kind, 'audio_path' => null, 'error' => null];
        }

        if ($kind === self::KIND_UNSUPPORTED) {
            return ['kind' => $kind, 'audio_path' => null, 'error' => 'Tipo de mídia não suportado.'];
        }

        $media = $this->mediaBytes($payload, $messageData);

        if ($media === null) {
            return ['kind' => $kind, 'audio_path' => null, 'error' => 'Não foi possível baixar a mídia da mensagem.'];
        }

        try {
            if ($kind === self::KIND_AUDIO) {
                return [
                    'kind' => $kind,
                    'audio_path' => $this->storeTemporaryAudio($media, $message),
                    'error' => null,
                ];
            }

            $this->persistAttachment($media, $messageData, $message);

            return ['kind' => $kind, 'audio_path' => null, 'error' => null];
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return ['kind' => $kind, 'audio_path' => null, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Relative path (local disk) of the temporary audio file, capped at
     * services.openai.audio_max_mb — the same ceiling the transcription
     * API accepts.
     */
    private function storeTemporaryAudio(array $media, AssistantMessage $message): string
    {
        $maxBytes = max(1, (int) config('services.openai.audio_max_mb', 25)) * 1024 * 1024;

        if (strlen($media['bytes']) > $maxBytes) {
            throw new RuntimeException('O áudio excede o limite de '.(int) config('services.openai.audio_max_mb', 25).' MB para transcrição.');
        }

        $extension = $this->extensionFromMime((string) $media['mime_type']) ?? 'ogg';
        $path = "assistant/{$message->company_id}/{$message->assistant_conversation_id}/tmp/".Str::uuid()->toString().".{$extension}";

        Storage::disk('local')->put($path, $media['bytes']);

        return $path;
    }

    private function persistAttachment(array $media, array $messageData, AssistantMessage $message): void
    {
        $filename = $this->filename($messageData) ?? 'arquivo-'.Str::uuid()->toString().'.bin';

        $temporaryPath = tempnam(sys_get_temp_dir(), 'assistant-wa');
        if ($temporaryPath === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário para o anexo.');
        }

        try {
            file_put_contents($temporaryPath, $media['bytes']);

            $this->uploader->persistFromPath($temporaryPath, $filename, $message);
        } finally {
            @unlink($temporaryPath);
        }
    }

    /**
     * @return array{bytes: string, mime_type: ?string}|null
     */
    private function mediaBytes(array $payload, array $messageData): ?array
    {
        $inline = $this->inlineBase64($messageData);

        if ($inline === null) {
            $inline = $this->downloadBase64($payload, $messageData);
        }

        if ($inline === null) {
            return null;
        }

        $bytes = base64_decode($inline['data'], true);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        return [
            'bytes' => $bytes,
            'mime_type' => $inline['mime_type'] ?? $this->mimeFromMessage($messageData),
        ];
    }

    /**
     * @return array{mime_type: ?string, data: string}|null
     */
    private function inlineBase64(array $messageData): ?array
    {
        $raw = data_get($messageData, 'message.base64')
            ?: data_get($messageData, 'base64')
            ?: data_get($messageData, 'message.audioMessage.base64')
            ?: data_get($messageData, 'message.imageMessage.base64')
            ?: data_get($messageData, 'message.documentMessage.base64');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);

        if (preg_match('/^data:(?<mime>[^;]+);base64,(?<data>.+)$/s', $raw, $matches)) {
            return [
                'mime_type' => $matches['mime'] ?: null,
                'data' => trim($matches['data']),
            ];
        }

        return [
            'mime_type' => $this->mimeFromMessage($messageData),
            'data' => $raw,
        ];
    }

    /**
     * @return array{mime_type: ?string, data: string}|null
     */
    private function downloadBase64(array $payload, array $messageData): ?array
    {
        $instance = (string) ($payload['instance'] ?? config('services.assistant.whatsapp.instance'));

        try {
            return $this->client->getBase64FromMediaMessage($instance, $messageData);
        } catch (Throwable $exception) {
            Log::info('Assistant WhatsApp: Evolution did not return media base64.', [
                'instance' => $instance,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function mimeFromMessage(array $messageData): ?string
    {
        $message = data_get($messageData, 'message', []);

        if (! is_array($message)) {
            return null;
        }

        foreach (['audioMessage', 'imageMessage', 'documentMessage', 'videoMessage', 'stickerMessage'] as $key) {
            $mime = data_get($message, $key.'.mimetype');
            if (is_string($mime) && $mime !== '') {
                return str($mime)->before(';')->trim()->toString();
            }
        }

        return null;
    }

    private function extensionFromMime(?string $mime): ?string
    {
        $mime = str((string) $mime)->before(';')->lower()->trim()->toString();

        return match ($mime) {
            'audio/ogg', 'audio/opus', 'application/ogg' => 'ogg',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            'audio/flac' => 'flac',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => null,
        };
    }
}
