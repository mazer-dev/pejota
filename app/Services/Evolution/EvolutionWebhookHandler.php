<?php

namespace App\Services\Evolution;

use App\Models\Company;
use App\Models\WhatsappAttachment;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ai\OpenAiAudioTranscriber;
use App\Services\Documents\AttachmentTextExtractor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EvolutionWebhookHandler
{
    public function __construct(
        private readonly AttachmentTextExtractor $extractor,
        private readonly OpenAiAudioTranscriber $transcriber,
        private readonly WhatsappConversationMatcher $matcher,
        private readonly WhatsappConversationTokenService $tokenService,
    ) {}

    public function handle(array $payload): int
    {
        $event = $this->event($payload);

        if ($event === 'MESSAGES_UPDATE') {
            return $this->handleMessageUpdate($payload);
        }

        if (! in_array($event, ['MESSAGES_UPSERT', 'SEND_MESSAGE'], true)) {
            return 0;
        }

        $messages = $this->messages($payload);
        $count = 0;

        foreach ($messages as $messageData) {
            if ($this->storeMessage($payload, $messageData)) {
                $count++;
            }
        }

        return $count;
    }

    private function storeMessage(array $payload, array $messageData): ?WhatsappMessage
    {
        $companyId = $this->companyId();
        $instance = (string) ($payload['instance'] ?? config('services.evolution.instance') ?? 'default');
        $remoteJid = $this->remoteJid($payload, $messageData);

        if ($remoteJid === null) {
            return null;
        }

        $fromMe = (bool) data_get($messageData, 'key.fromMe', data_get($messageData, 'fromMe', false));
        $phoneNumber = $this->phoneNumber($payload, $messageData, $remoteJid);
        $sentAt = $this->sentAt($payload, $messageData);
        $matchedClient = null;
        $conversation = WhatsappConversation::allTenants()->firstOrNew([
            'company_id' => $companyId,
            'evolution_instance' => $instance,
            'remote_jid' => $remoteJid,
        ]);

        if (! $conversation->client_id && $phoneNumber) {
            $conversation->phone_number = $conversation->phone_number ?: $phoneNumber;
            $matchedClient = $this->matcher->bestClientForConversation($conversation);
        }

        $conversation->fill([
            'client_id' => $conversation->client_id ?: ($matchedClient->id ?? null),
            'phone_number' => $conversation->phone_number ?: $phoneNumber,
            'push_name' => $this->pushName($messageData) ?: $conversation->push_name,
            'last_message_at' => $sentAt,
            'status' => $conversation->status ?: 'open',
        ]);

        $messageId = $this->messageId($messageData);
        $message = $messageId
            ? WhatsappMessage::allTenants()->firstOrNew([
                'company_id' => $companyId,
                'evolution_instance' => $instance,
                'remote_message_id' => $messageId,
            ])
            : new WhatsappMessage;

        $isNew = ! $message->exists;

        $message->fill([
            'company_id' => $companyId,
            'whatsapp_conversation_id' => $conversation->id,
            'client_id' => $conversation->client_id,
            'project_id' => $conversation->project_id,
            'evolution_instance' => $instance,
            'remote_message_id' => $messageId,
            'remote_jid' => $remoteJid,
            'sender_jid' => (string) (data_get($messageData, 'key.participant') ?: $payload['sender'] ?? null),
            'sender_name' => $this->pushName($messageData),
            'from_me' => $fromMe,
            'message_type' => $this->messageType($messageData),
            'text' => $this->messageText($messageData),
            'status' => data_get($messageData, 'status'),
            'sent_at' => $sentAt,
            'payload' => $payload,
        ]);

        $conversation->save();
        $message->whatsapp_conversation_id = $conversation->id;
        $message->save();

        if ($isNew && ! $fromMe) {
            $conversation->increment('unread_count');
        }

        $conversation->forceFill([
            'last_message_at' => $sentAt,
        ])->save();

        $this->storeAttachment($message, $messageData);
        $this->tokenService->refresh($conversation);

        return $message;
    }

    private function handleMessageUpdate(array $payload): int
    {
        $companyId = $this->companyId();
        $instance = (string) ($payload['instance'] ?? config('services.evolution.instance') ?? 'default');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $messageId = (string) (data_get($data, 'keyId') ?: data_get($data, 'key.id') ?: data_get($data, 'id'));

        if ($messageId === '') {
            return 0;
        }

        $message = WhatsappMessage::allTenants()
            ->where('company_id', $companyId)
            ->where('evolution_instance', $instance)
            ->where('remote_message_id', $messageId)
            ->first();

        if (! $message) {
            return 0;
        }

        $message->forceFill([
            'status' => (string) (data_get($data, 'status') ?: data_get($data, 'update.status') ?: $message->status),
            'payload' => $payload,
        ])->save();

        return 1;
    }

    private function storeAttachment(WhatsappMessage $message, array $messageData): void
    {
        if ($message->attachments()->exists()) {
            return;
        }

        $media = $this->mediaInfo($messageData);
        if ($media === null && ! $this->isMediaType($message->message_type)) {
            return;
        }

        $base64 = $this->base64($messageData);
        $attachment = new WhatsappAttachment([
            'company_id' => $message->company_id,
            'whatsapp_message_id' => $message->id,
            'original_filename' => $media['filename'] ?? null,
            'mime_type' => $media['mime_type'] ?? $base64['mime_type'] ?? null,
            'extension' => $media['extension'] ?? $this->extensionFromMime($media['mime_type'] ?? $base64['mime_type'] ?? null),
            'status' => $base64 ? 'stored' : 'metadata_only',
            'payload' => $media,
        ]);

        if ($base64) {
            $bytes = base64_decode($base64['data'], true);
            if ($bytes !== false) {
                $extension = $attachment->extension ?: 'bin';
                $path = 'whatsapp/'.$message->company_id.'/'.$message->id.'/'.Str::uuid().'.'.$extension;

                Storage::disk('local')->put($path, $bytes);

                $attachment->forceFill([
                    'path' => $path,
                    'size_bytes' => strlen($bytes),
                    'sha256' => hash('sha256', $bytes),
                ]);

                $this->enrichAttachment($attachment, storage_path('app/'.$path), $message);
            }
        }

        $attachment->save();
    }

    private function enrichAttachment(WhatsappAttachment $attachment, string $filePath, WhatsappMessage $message): void
    {
        try {
            if (str_starts_with((string) $attachment->mime_type, 'audio/') && (bool) config('services.evolution.transcribe_audio', true)) {
                $attachment->transcription_text = $this->transcriber->transcribe($filePath);
                if (! $message->text) {
                    $message->forceFill(['text' => $attachment->transcription_text])->save();
                }
            } else {
                $attachment->extracted_text = $this->extractor->extract($filePath, $attachment->mime_type, $attachment->extension);
            }
        } catch (\Throwable $exception) {
            $attachment->status = 'error';
            $attachment->error = $exception->getMessage();

            Log::warning('Failed to enrich WhatsApp attachment.', [
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function messages(array $payload): array
    {
        $data = $payload['data'] ?? [];

        if (is_array($data) && array_is_list($data)) {
            return array_filter($data, 'is_array');
        }

        return is_array($data) ? [$data] : [];
    }

    private function event(array $payload): string
    {
        return str((string) ($payload['event'] ?? ''))
            ->replace('.', '_')
            ->upper()
            ->toString();
    }

    private function remoteJid(array $payload, array $messageData): ?string
    {
        $jid = data_get($messageData, 'key.remoteJid')
            ?: data_get($messageData, 'remoteJid')
            ?: data_get($payload, 'sender');

        return is_string($jid) && $jid !== '' ? $jid : null;
    }

    private function messageId(array $messageData): ?string
    {
        $id = data_get($messageData, 'key.id') ?: data_get($messageData, 'keyId') ?: data_get($messageData, 'id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function phoneNumber(array $payload, array $messageData, string $remoteJid): ?string
    {
        $candidate = str_contains($remoteJid, '@lid')
            ? (string) ($payload['sender'] ?? '')
            : $remoteJid;

        $number = preg_replace('/\D+/', '', str($candidate)->before('@')->toString());

        return $number === '' ? null : $number;
    }

    private function pushName(array $messageData): ?string
    {
        $name = data_get($messageData, 'pushName') ?: data_get($messageData, 'senderName');

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    private function sentAt(array $payload, array $messageData): Carbon
    {
        $timestamp = data_get($messageData, 'messageTimestamp');

        if (is_numeric($timestamp)) {
            $timestamp = (int) $timestamp;
            if ($timestamp > 9999999999) {
                $timestamp = (int) floor($timestamp / 1000);
            }

            return Carbon::createFromTimestamp($timestamp);
        }

        return Carbon::parse((string) ($payload['date_time'] ?? now()));
    }

    private function messageType(array $messageData): string
    {
        $type = data_get($messageData, 'messageType');
        if (is_string($type) && $type !== '') {
            return str($type)->replace('Message', '')->snake()->toString();
        }

        $message = data_get($messageData, 'message', []);
        foreach (array_keys(is_array($message) ? $message : []) as $key) {
            if ($key === 'conversation') {
                return 'text';
            }

            if (str_ends_with($key, 'Message')) {
                return str($key)->replace('Message', '')->snake()->toString();
            }
        }

        return 'text';
    }

    private function messageText(array $messageData): ?string
    {
        $candidates = [
            data_get($messageData, 'message.conversation'),
            data_get($messageData, 'message.extendedTextMessage.text'),
            data_get($messageData, 'message.imageMessage.caption'),
            data_get($messageData, 'message.videoMessage.caption'),
            data_get($messageData, 'message.documentMessage.caption'),
            data_get($messageData, 'message.documentWithCaptionMessage.message.documentMessage.caption'),
            data_get($messageData, 'message.buttonsResponseMessage.selectedDisplayText'),
            data_get($messageData, 'message.listResponseMessage.title'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function mediaInfo(array $messageData): ?array
    {
        $message = data_get($messageData, 'message', []);
        if (! is_array($message)) {
            return null;
        }

        foreach (['audioMessage', 'imageMessage', 'videoMessage', 'documentMessage', 'stickerMessage'] as $key) {
            $media = data_get($message, $key);
            if (is_array($media)) {
                $mime = data_get($media, 'mimetype');
                $filename = data_get($media, 'fileName');

                return [
                    'type' => str($key)->replace('Message', '')->snake()->toString(),
                    'mime_type' => is_string($mime) ? $mime : null,
                    'filename' => is_string($filename) ? $filename : null,
                    'extension' => is_string($filename) ? pathinfo($filename, PATHINFO_EXTENSION) : $this->extensionFromMime($mime),
                ];
            }
        }

        return null;
    }

    private function base64(array $messageData): ?array
    {
        $raw = data_get($messageData, 'message.base64')
            ?: data_get($messageData, 'base64')
            ?: data_get($messageData, 'message.audioMessage.base64')
            ?: data_get($messageData, 'message.imageMessage.base64')
            ?: data_get($messageData, 'message.videoMessage.base64')
            ?: data_get($messageData, 'message.documentMessage.base64');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        if (preg_match('/^data:(?<mime>[^;]+);base64,(?<data>.+)$/s', $raw, $matches)) {
            return [
                'mime_type' => $matches['mime'],
                'data' => $matches['data'],
            ];
        }

        return [
            'mime_type' => null,
            'data' => $raw,
        ];
    }

    private function isMediaType(string $type): bool
    {
        return in_array($type, ['audio', 'image', 'video', 'document', 'sticker'], true);
    }

    private function extensionFromMime(?string $mime): ?string
    {
        return match ($mime) {
            'audio/ogg', 'audio/opus' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/wav', 'audio/x-wav' => 'wav',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => null,
        };
    }

    private function companyId(): int
    {
        $configured = config('services.evolution.default_company_id');
        if ($configured) {
            return (int) $configured;
        }

        return (int) Company::query()->value('id');
    }
}
