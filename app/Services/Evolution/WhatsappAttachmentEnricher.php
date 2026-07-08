<?php

namespace App\Services\Evolution;

use App\Models\WhatsappAttachment;
use App\Models\WhatsappMessage;
use App\Services\Ai\OpenAiAudioTranscriber;
use App\Services\Ai\OpenAiImageDescriber;
use App\Services\Documents\AttachmentTextExtractor;
use Illuminate\Support\Facades\Log;
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
                $attachment->transcription_text = $this->transcriber->transcribe($filePath);

                if ($message && ! $message->text) {
                    $message->forceFill(['text' => $attachment->transcription_text])->save();
                }

                return;
            }

            if (str_starts_with((string) $attachment->mime_type, 'image/') && (bool) config('services.openai.describe_images', true)) {
                $attachment->extracted_text = $this->imageDescriber->describe($filePath, $attachment->mime_type);

                return;
            }

            $attachment->extracted_text = $this->extractor->extract($filePath, $attachment->mime_type, $attachment->extension);
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
}
