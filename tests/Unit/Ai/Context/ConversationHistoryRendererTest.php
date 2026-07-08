<?php

namespace Tests\Unit\Ai\Context;

use App\Models\WhatsappAttachment;
use App\Models\WhatsappMessage;
use App\Services\Ai\Context\ConversationHistoryRenderer;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ConversationHistoryRendererTest extends TestCase
{
    public function test_it_renders_messages_oldest_first_with_the_given_from_me_label(): void
    {
        $older = $this->message(id: 1, fromMe: false, senderName: 'Vivianne', text: 'Oi, tudo bem?', sentAt: '2026-01-01 10:00:00');
        $newer = $this->message(id: 2, fromMe: true, senderName: null, text: 'Tudo certo, e você?', sentAt: '2026-01-01 10:05:00');

        $context = (new ConversationHistoryRenderer)->render(
            new Collection([$newer, $older]),
            fromMeLabel: 'Pejota',
        );

        $lines = explode("\n", $context);

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('Vivianne | Oi, tudo bem?', $lines[0]);
        $this->assertStringContainsString('Pejota | Tudo certo, e você?', $lines[1]);
    }

    public function test_it_limits_to_the_most_recent_messages_when_a_limit_is_given(): void
    {
        $messages = new Collection([
            $this->message(id: 1, fromMe: false, senderName: 'Cliente', text: 'Mensagem 1', sentAt: '2026-01-01 10:00:00'),
            $this->message(id: 2, fromMe: false, senderName: 'Cliente', text: 'Mensagem 2', sentAt: '2026-01-01 10:01:00'),
            $this->message(id: 3, fromMe: false, senderName: 'Cliente', text: 'Mensagem 3', sentAt: '2026-01-01 10:02:00'),
        ]);

        $context = (new ConversationHistoryRenderer)->render($messages, limit: 2);

        $this->assertStringNotContainsString('Mensagem 1', $context);
        $this->assertStringContainsString('Mensagem 2', $context);
        $this->assertStringContainsString('Mensagem 3', $context);
    }

    public function test_it_keeps_full_history_when_limit_is_null(): void
    {
        $messages = new Collection([
            $this->message(id: 1, fromMe: false, senderName: 'Cliente', text: 'Mensagem 1', sentAt: '2026-01-01 10:00:00'),
            $this->message(id: 2, fromMe: false, senderName: 'Cliente', text: 'Mensagem 2', sentAt: '2026-01-01 10:01:00'),
        ]);

        $context = (new ConversationHistoryRenderer)->render($messages, limit: null);

        $this->assertStringContainsString('Mensagem 1', $context);
        $this->assertStringContainsString('Mensagem 2', $context);
    }

    public function test_it_formats_attachments_with_transcription_and_extracted_text(): void
    {
        $message = $this->message(id: 1, fromMe: false, senderName: 'Cliente', text: null, sentAt: '2026-01-01 10:00:00');

        $message->setRelation('attachments', new Collection([
            new WhatsappAttachment([
                'original_filename' => 'audio.ogg',
                'mime_type' => 'audio/ogg',
                'transcription_text' => 'Preciso confirmar os emails.',
            ]),
            new WhatsappAttachment([
                'original_filename' => 'briefing.pdf',
                'mime_type' => 'application/pdf',
                'extracted_text' => 'Existem 19 emails na cadência.',
            ]),
            new WhatsappAttachment([
                'original_filename' => 'unknown.bin',
                'mime_type' => 'application/octet-stream',
            ]),
        ]));

        $context = (new ConversationHistoryRenderer)->render(new Collection([$message]));

        $this->assertStringContainsString('Anexos:', $context);
        $this->assertStringContainsString('Anexo (audio.ogg - audio/ogg) - transcrição de áudio:', $context);
        $this->assertStringContainsString('Preciso confirmar os emails.', $context);
        $this->assertStringContainsString('Anexo (briefing.pdf - application/pdf) - conteúdo processado:', $context);
        $this->assertStringContainsString('Existem 19 emails na cadência.', $context);
        $this->assertStringContainsString('Anexo (unknown.bin - application/octet-stream) - sem texto extraído salvo.', $context);
    }

    private function message(int $id, bool $fromMe, ?string $senderName, ?string $text, string $sentAt): WhatsappMessage
    {
        $message = new WhatsappMessage([
            'from_me' => $fromMe,
            'sender_name' => $senderName,
            'text' => $text,
            'sent_at' => $sentAt,
        ]);

        $message->id = $id;
        $message->setRelation('attachments', new Collection);

        return $message;
    }
}
