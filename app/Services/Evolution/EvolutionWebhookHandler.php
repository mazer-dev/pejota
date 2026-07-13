<?php

namespace App\Services\Evolution;

use App\Models\Company;
use App\Models\WhatsappAttachment;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class EvolutionWebhookHandler
{
    public function __construct(
        private readonly EvolutionApiClient $client,
        private readonly WhatsappAttachmentEnricher $attachmentEnricher,
        private readonly WhatsappJidNormalizer $normalizer,
        private readonly WhatsappConversationTokenService $tokenService,
    ) {}

    /**
     * @param  bool  $dispatchSuggestions  Kept for backwards-compatible callers. Automatic
     *                                     AI analysis is intentionally disabled; every AI
     *                                     feature is initiated by an explicit UI action.
     * @param  bool  $withMedia  When false, skips media downloads and AI enrichment —
     *                           required on request-bound paths (chat polling), where the
     *                           extra HTTP calls and AI CLI runs exhaust memory/time.
     */
    public function handle(
        array $payload,
        bool $dispatchSuggestions = true,
        bool $withMedia = true,
        bool $refreshTokens = true,
    ): int {
        $event = $this->event($payload);

        if ($event === 'MESSAGES_UPDATE') {
            return $this->handleMessageUpdate($payload);
        }

        if (! in_array($event, ['MESSAGES_UPSERT', 'SEND_MESSAGE'], true)) {
            return 0;
        }

        $messages = $this->messages($payload);
        $count = 0;
        $conversations = [];

        foreach ($messages as $messageData) {
            if ($message = $this->storeMessage($payload, $messageData, $withMedia)) {
                if ($message->wasRecentlyCreated) {
                    $count++;
                }
                $conversations[$message->whatsapp_conversation_id] = $message->conversation;
            }
        }

        if ($refreshTokens) {
            foreach ($conversations as $conversation) {
                $this->tokenService->refresh($conversation);
            }
        }

        return $count;
    }

    private function storeMessage(array $payload, array $messageData, bool $withMedia = true): ?WhatsappMessage
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
        $conversation = $this->conversationForMessage(
            $companyId,
            $instance,
            $this->identityJids($payload, $messageData),
            $phoneNumber,
        );

        // Manually-created conversations are the allowlist. Unknown identities
        // stop here, before messages, files, token counting or AI work.
        if (! $conversation) {
            return null;
        }

        $latestAt = $conversation->last_message_at && $conversation->last_message_at->gt($sentAt)
            ? $conversation->last_message_at
            : $sentAt;

        $conversation->fill([
            'phone_number' => $conversation->phone_number ?: $phoneNumber,
            'push_name' => ($fromMe ? null : $this->pushName($messageData)) ?: $conversation->push_name,
            'last_message_at' => $latestAt,
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
        $messageText = $this->messageText($messageData);

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
            'text' => $messageText ?? $message->text,
            'status' => data_get($messageData, 'status'),
            'sent_at' => $sentAt,
            'payload' => $this->messagePayload($payload, $messageData),
        ]);

        $conversation->save();
        $message->whatsapp_conversation_id = $conversation->id;
        $message->save();
        $message->setRelation('conversation', $conversation);

        if ($isNew && ! $fromMe) {
            $conversation->increment('unread_count');
        }

        $this->storeAttachment($message, $messageData, $withMedia);

        return $message;
    }

    /**
     * The sync path batches dozens of records in the payload's data key;
     * persisting the whole batch on every message exhausted memory and
     * bloated the table, so each message keeps only its own record. Inline
     * media base64 is dropped — the binary lives in the attachment file.
     *
     * @return array<string, mixed>
     */
    private function messagePayload(array $payload, array $messageData): array
    {
        unset($messageData['base64']);

        if (is_array($messageData['message'] ?? null)) {
            unset($messageData['message']['base64']);

            foreach (['audioMessage', 'imageMessage', 'videoMessage', 'documentMessage', 'stickerMessage'] as $key) {
                if (is_array($messageData['message'][$key] ?? null)) {
                    unset($messageData['message'][$key]['base64']);
                }
            }
        }

        $payload['data'] = $messageData;

        return $payload;
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

    private function storeAttachment(WhatsappMessage $message, array $messageData, bool $withMedia = true): void
    {
        $attachment = $this->attachmentForMessage($message);
        if ($attachment?->path) {
            if ($withMedia && $this->needsEnrichment($attachment)) {
                $this->enrichAttachment($attachment, storage_path('app/'.$attachment->path), $message);
                $attachment->save();
            }

            return;
        }

        $media = $this->mediaInfo($messageData);
        if ($media === null && ! $this->isMediaType($message->message_type)) {
            return;
        }

        $base64 = $this->base64($messageData) ?: ($withMedia ? $this->downloadBase64($message, $messageData) : null);
        $mimeType = $media['mime_type'] ?? $base64['mime_type'] ?? $attachment?->mime_type;
        $extension = $media['extension'] ?? $attachment?->extension ?? $this->extensionFromMime($mimeType);
        $attachment ??= new WhatsappAttachment([
            'company_id' => $message->company_id,
            'whatsapp_message_id' => $message->id,
        ]);

        $attachment->fill([
            'company_id' => $message->company_id,
            'whatsapp_message_id' => $message->id,
            'original_filename' => $media['filename'] ?? $attachment->original_filename,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'status' => $base64 ? 'stored' : ($attachment->status ?: 'metadata_only'),
            'payload' => $media ?: $attachment->payload,
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

                if ($withMedia) {
                    $this->enrichAttachment($attachment, storage_path('app/'.$path), $message);
                }
            }
        }

        $attachment->save();
    }

    private function attachmentForMessage(WhatsappMessage $message): ?WhatsappAttachment
    {
        return $message->attachments()->whereNotNull('path')->first()
            ?: $message->attachments()->first();
    }

    /**
     * @param  array<int, string>  $identityJids
     */
    private function conversationForMessage(int $companyId, string $instance, array $identityJids, ?string $phoneNumber): ?WhatsappConversation
    {
        $incomingJids = collect($identityJids)
            ->filter(fn ($jid): bool => is_string($jid) && trim($jid) !== '' && ! str_contains($jid, '@g.us'))
            ->unique()
            ->values();

        if ($incomingJids->isEmpty()) {
            return null;
        }

        $baseQuery = WhatsappConversation::allTenants()
            ->where('company_id', $companyId)
            ->where('evolution_instance', $instance);

        $conversation = (clone $baseQuery)
            ->whereIn('remote_jid', $incomingJids->all())
            ->orderByDesc('last_message_at')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $incomingNumbers = $incomingJids
            ->reject(fn (string $jid): bool => str_contains($jid, '@lid'))
            ->flatMap(fn (string $jid): array => $this->normalizer->candidates($jid))
            ->merge($phoneNumber ? $this->normalizer->candidates($phoneNumber) : [])
            ->unique()
            ->values()
            ->all();

        if ($incomingNumbers === []) {
            return null;
        }

        return (clone $baseQuery)
            ->get()
            ->first(function (WhatsappConversation $candidate) use ($incomingNumbers): bool {
                $allowedNumbers = collect([$candidate->phone_number, $candidate->remote_jid])
                    ->filter(fn ($value): bool => is_string($value) && ! str_contains($value, '@lid'))
                    ->flatMap(fn (string $value): array => $this->normalizer->candidates($value))
                    ->unique()
                    ->all();

                return array_intersect($incomingNumbers, $allowedNumbers) !== [];
            });
    }

    private function enrichAttachment(WhatsappAttachment $attachment, string $filePath, WhatsappMessage $message): void
    {
        $this->attachmentEnricher->enrich($attachment, $filePath, $message);
    }

    private function needsEnrichment(WhatsappAttachment $attachment): bool
    {
        return blank($attachment->transcription_text)
            && blank($attachment->extracted_text)
            && blank($attachment->error);
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

    /**
     * Evolution may identify the same person by a numeric JID, a LID, or an
     * alternate JID. All supplied identities participate in allowlist lookup.
     *
     * @return array<int, string>
     */
    private function identityJids(array $payload, array $messageData): array
    {
        return collect([
            data_get($messageData, 'key.remoteJid'),
            data_get($messageData, 'key.remoteJidAlt'),
            data_get($messageData, 'remoteJid'),
            data_get($messageData, 'remoteJidAlt'),
            data_get($payload, 'sender'),
        ])
            ->filter(fn ($jid): bool => is_string($jid) && trim($jid) !== '')
            ->map(fn (string $jid): string => trim($jid))
            ->unique()
            ->values()
            ->all();
    }

    private function messageId(array $messageData): ?string
    {
        $id = data_get($messageData, 'key.id') ?: data_get($messageData, 'keyId') ?: data_get($messageData, 'id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function phoneNumber(array $payload, array $messageData, string $remoteJid): ?string
    {
        foreach ($this->identityJids($payload, $messageData) as $candidate) {
            if (str_contains($candidate, '@lid') || str_contains($candidate, '@g.us')) {
                continue;
            }

            $number = $this->normalizer->digits($candidate);
            if ($number !== '') {
                return $number;
            }
        }

        return str_contains($remoteJid, '@lid') ? null : ($this->normalizer->digits($remoteJid) ?: null);
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

        return $this->normaliseBase64($raw);
    }

    private function downloadBase64(WhatsappMessage $message, array $messageData): ?array
    {
        try {
            return $this->client->getBase64FromMediaMessage($message->evolution_instance, $messageData);
        } catch (Throwable $exception) {
            Log::info('Evolution did not return media base64 for WhatsApp message.', [
                'message_id' => $message->id,
                'remote_message_id' => $message->remote_message_id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{mime_type: ?string, data: string}|null
     */
    private function normaliseBase64(mixed $raw, ?string $mimeType = null): ?array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);
        if (preg_match('/^data:(?<mime>[^;]+);base64,(?<data>.+)$/s', $raw, $matches)) {
            return [
                'mime_type' => $matches['mime'] ?: $mimeType,
                'data' => trim($matches['data']),
            ];
        }

        return [
            'mime_type' => $mimeType,
            'data' => $raw,
        ];
    }

    private function isMediaType(string $type): bool
    {
        return in_array($type, ['audio', 'image', 'video', 'document', 'sticker'], true);
    }

    private function extensionFromMime(?string $mime): ?string
    {
        $mime = str((string) $mime)->before(';')->lower()->trim()->toString();

        return match ($mime) {
            'audio/ogg', 'audio/opus', 'application/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            'audio/flac' => 'flac',
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
