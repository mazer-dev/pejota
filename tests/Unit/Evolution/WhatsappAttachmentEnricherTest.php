<?php

namespace Tests\Unit\Evolution;

use App\Models\WhatsappAttachment;
use App\Services\Ai\OpenAiAudioTranscriber;
use App\Services\Ai\OpenAiImageDescriber;
use App\Services\Documents\AttachmentTextExtractor;
use App\Services\Evolution\WhatsappAttachmentEnricher;
use Mockery;
use Tests\TestCase;

class WhatsappAttachmentEnricherTest extends TestCase
{
    public function test_it_transcribes_bin_audio_with_extension_from_mime_type(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'pejota-audio-');
        file_put_contents($sourcePath, 'fake ogg bytes');

        $transcriber = Mockery::mock(OpenAiAudioTranscriber::class);
        $transcriber->shouldReceive('transcribe')
            ->once()
            ->with(Mockery::on(fn (string $path): bool => str_ends_with($path, '.ogg') && is_file($path)))
            ->andReturn('audio transcrito');

        $enricher = new WhatsappAttachmentEnricher(
            Mockery::mock(AttachmentTextExtractor::class),
            $transcriber,
            Mockery::mock(OpenAiImageDescriber::class),
        );

        $attachment = new WhatsappAttachment([
            'mime_type' => 'audio/ogg; codecs=opus',
        ]);

        try {
            $enricher->enrich($attachment, $sourcePath);
        } finally {
            @unlink($sourcePath);
        }

        $this->assertSame('audio transcrito', $attachment->transcription_text);
    }
}
