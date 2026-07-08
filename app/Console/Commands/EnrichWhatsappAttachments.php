<?php

namespace App\Console\Commands;

use App\Models\WhatsappAttachment;
use App\Services\Evolution\WhatsappAttachmentEnricher;
use App\Services\Evolution\WhatsappConversationTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EnrichWhatsappAttachments extends Command
{
    protected $signature = 'whatsapp:enrich-attachments
        {--retry-errors : Reprocessar anexos que falharam antes}
        {--limit=200 : Número máximo de anexos para processar}';

    protected $description = 'Transcreve áudios e extrai/descreve anexos de WhatsApp já salvos no disco.';

    public function handle(WhatsappAttachmentEnricher $enricher, WhatsappConversationTokenService $tokenService): int
    {
        $retryErrors = (bool) $this->option('retry-errors');
        $limit = max(1, (int) $this->option('limit'));
        $processed = 0;
        $refreshedConversationIds = [];

        $query = WhatsappAttachment::allTenants()
            ->with('message.conversation')
            ->whereNotNull('path')
            ->where(function ($query) {
                $query->whereNull('transcription_text')
                    ->orWhere('transcription_text', '');
            })
            ->where(function ($query) {
                $query->whereNull('extracted_text')
                    ->orWhere('extracted_text', '');
            })
            ->limit($limit);

        if (! $retryErrors) {
            $query->where(function ($query) {
                $query->whereNull('error')
                    ->orWhere('error', '');
            });
        }

        foreach ($query->get() as $attachment) {
            if (! $attachment->path || ! Storage::disk($attachment->disk)->exists($attachment->path)) {
                continue;
            }

            $path = Storage::disk($attachment->disk)->path($attachment->path);
            $message = $attachment->message;

            $enricher->enrich($attachment, $path, $message);
            $attachment->save();
            $processed++;

            if ($message?->conversation) {
                $refreshedConversationIds[$message->conversation->id] = $message->conversation;
            }
        }

        foreach ($refreshedConversationIds as $conversation) {
            $tokenService->refresh($conversation);
        }

        $this->info("Anexos processados: {$processed}");

        return self::SUCCESS;
    }
}
