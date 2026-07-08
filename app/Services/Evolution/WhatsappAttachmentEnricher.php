<?php

namespace App\Services\Evolution;

use App\Models\WhatsappAttachment;
use App\Models\WhatsappMessage;
use App\Services\Ai\OpenAiAudioTranscriber;
use App\Services\Ai\OpenAiImageDescriber;
use App\Services\Documents\AttachmentTextExtractor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WhatsappAttachmentEnricher
{
    public function __construct(
        private readonly AttachmentTextExtractor $extractor,
        private readonly OpenAiAudioTranscriber $transcriber,
        private readonly OpenAiImageDescriber $imageDescriber,
    ) {}

    public function enrich(WhatsappAttachment $attachment, string $filePath, ?WhatsappMessage $message = null): void
    {
        try {
            if (str_starts_with((string) $attachment->mime_type, 'audio/') && (bool) config('services.evolution.transcribe_audio', true)) {
                $attachment->transcription_text = $this->transcribeAudio($attachment, $filePath);
                $this->markAsProcessed($attachment);

                if ($message && ! $message->text) {
                    $message->forceFill(['text' => $attachment->transcription_text])->save();
                }

                return;
            }

            if (str_starts_with((string) $attachment->mime_type, 'image/') && (bool) config('services.openai.describe_images', true)) {
                $attachment->extracted_text = $this->imageDescriber->describe($filePath, $attachment->mime_type);
                $this->markAsProcessed($attachment);

                return;
            }

            $attachment->extracted_text = $this->extractor->extract($filePath, $attachment->mime_type, $attachment->extension);
            $this->markAsProcessed($attachment);
        } catch (Throwable $exception) {
            $attachment->status = 'error';
            $attachment->error = $exception->getMessage();

            Log::warning('Failed to enrich WhatsApp attachment.', [
                'attachment_id' => $attachment->id,
                'message_id' => $message?->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function transcribeAudio(WhatsappAttachment $attachment, string $filePath): string
    {
        $preparedPath = $this->audioPathWithSupportedExtension($attachment, $filePath);

        try {
            return $this->transcriber->transcribe($preparedPath);
        } finally {
            if ($preparedPath !== $filePath && is_file($preparedPath)) {
                @unlink($preparedPath);
            }
        }
    }

    private function audioPathWithSupportedExtension(WhatsappAttachment $attachment, string $filePath): string
    {
        $currentExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($currentExtension !== '' && $currentExtension !== 'bin') {
            return $filePath;
        }

        $extension = $this->audioExtensionFromMime($attachment->mime_type ?: mime_content_type($filePath) ?: null);
        if ($extension === null) {
            return $filePath;
        }

        $targetPath = sys_get_temp_dir().'/pejota-whatsapp-audio-'.Str::uuid().'.'.$extension;
        copy($filePath, $targetPath);

        return $targetPath;
    }

    private function audioExtensionFromMime(?string $mimeType): ?string
    {
        $mimeType = str((string) $mimeType)->before(';')->lower()->trim()->toString();

        return match ($mimeType) {
            'audio/ogg', 'audio/opus', 'application/ogg' => 'ogg',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            'audio/flac' => 'flac',
            default => null,
        };
    }

    private function markAsProcessed(WhatsappAttachment $attachment): void
    {
        $attachment->status = 'stored';
        $attachment->error = null;
    }
}
