<?php

namespace App\Services\Evolution;

use App\Jobs\ProcessAssistantWhatsappMessage;
use App\Livewire\AssistantChat;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Company;
use App\Models\User;
use App\Services\Ai\AssistantInvoiceService;
use App\Services\Ai\AssistantWhatsappMediaIngestor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * WhatsApp gateway for the Data Assistant, bound to a dedicated Evolution
 * instance. Completely separate from the client-facing WhatsApp flow: it
 * creates AssistantConversation/AssistantMessage records (visible in the
 * web panel), never WhatsappConversation/WhatsappMessage.
 *
 * A "session" is the open (closed_at IS NULL) whatsapp-channel conversation
 * for the sender's number; `#fim` closes it and the next message opens a
 * fresh one. Numbers outside the allowlist are silently ignored (log only).
 * Business failures never bubble up — the webhook must not 500.
 */
class AssistantWhatsappWebhookHandler
{
    public const ACK_TEXT = '⏳ Consultando os dados… já respondo.';

    public const SESSION_CLOSED_TEXT = '✅ Atendimento encerrado. Sua próxima mensagem abre um novo atendimento.';

    public const NO_SESSION_TEXT = 'Não há atendimento ativo. É só mandar sua pergunta para começar um novo.';

    public const UNSUPPORTED_MEDIA_TEXT = 'Tipo de mídia não suportado. Envie texto, áudio, imagem, PDF, DOCX, XLSX, CSV ou TXT.';

    public const AUDIO_PLACEHOLDER = '[Áudio recebido — transcrevendo…]';

    public function __construct(
        private readonly EvolutionApiClient $client,
        private readonly WhatsappJidNormalizer $normalizer,
        private readonly AssistantWhatsappMediaIngestor $mediaIngestor,
        private readonly AssistantInvoiceService $invoiceService,
    ) {}

    public function handles(array $payload): bool
    {
        if (! (bool) config('services.assistant.whatsapp.enabled', false)) {
            return false;
        }

        $instance = (string) config('services.assistant.whatsapp.instance');

        return $instance !== '' && (string) ($payload['instance'] ?? '') === $instance;
    }

    public function handle(array $payload): int
    {
        if ($this->event($payload) !== 'MESSAGES_UPSERT') {
            return 0;
        }

        $count = 0;

        foreach ($this->messages($payload) as $messageData) {
            try {
                if ($this->handleMessage($payload, $messageData)) {
                    $count++;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $count;
    }

    private function handleMessage(array $payload, array $messageData): bool
    {
        if ((bool) data_get($messageData, 'key.fromMe', data_get($messageData, 'fromMe', false))) {
            return false;
        }

        $number = $this->normalizer->senderNumber($payload, $messageData);
        $allowedNumbers = (array) config('services.assistant.whatsapp.allowed_numbers', []);

        if (! $this->normalizer->isAllowed($number, $allowedNumbers)) {
            Log::info('Assistant WhatsApp: message from non-allowlisted number ignored.', [
                'number' => $number,
            ]);

            return false;
        }

        $companyId = $this->companyId();
        $owner = $this->owner($companyId);

        if ($owner === null) {
            Log::warning('Assistant WhatsApp: no owner user found for company.', [
                'company_id' => $companyId,
            ]);

            return false;
        }

        $text = trim((string) $this->messageText($messageData));
        $session = $this->activeSession($companyId, $number);

        if ($this->isCommand($text, (string) config('services.assistant.whatsapp.end_command', '#fim'))) {
            $this->endSession($session, $number);

            return true;
        }

        if ($this->isCommand($text, (string) config('services.assistant.whatsapp.help_command', '#ajuda'))) {
            $this->sendText($number, $this->helpText());

            return true;
        }

        if ($session !== null && $this->confirmPendingInvoice($session, $owner, $text)) {
            return true;
        }

        $mediaKind = $this->mediaIngestor->kind($messageData);

        if ($mediaKind === AssistantWhatsappMediaIngestor::KIND_UNSUPPORTED) {
            $this->sendText($number, self::UNSUPPORTED_MEDIA_TEXT);

            return true;
        }

        if ($text === '' && $mediaKind === AssistantWhatsappMediaIngestor::KIND_NONE) {
            return false;
        }

        $session ??= $this->openSession($companyId, $owner, $number, $payload, $messageData, $text);

        $message = $session->messages()->create([
            'company_id' => $companyId,
            'role' => AssistantMessage::ROLE_USER,
            'content' => $this->messageContent($text, $mediaKind),
        ]);

        $audioPath = null;

        if ($mediaKind !== AssistantWhatsappMediaIngestor::KIND_NONE) {
            $ingested = $this->mediaIngestor->ingest($payload, $messageData, $message);

            if ($ingested['error'] !== null) {
                $this->sendText($number, '⚠️ '.$ingested['error']);

                if ($text === '') {
                    $message->delete();

                    return true;
                }
            }

            $audioPath = $ingested['audio_path'];
        }

        $session->touch();

        $this->maybeSendAck($session, $message, $number);

        ProcessAssistantWhatsappMessage::dispatch($session, $owner, $message, $audioPath)
            ->delay(now()->addSeconds(max(0, (int) config('services.assistant.whatsapp.debounce_seconds', 15))));

        return true;
    }

    private function isCommand(string $text, string $command): bool
    {
        return $command !== '' && mb_strtolower($text) === mb_strtolower($command);
    }

    private function endSession(?AssistantConversation $session, string $number): void
    {
        if ($session === null) {
            $this->sendText($number, self::NO_SESSION_TEXT);

            return;
        }

        $this->invoiceService->clearPending($session);
        $session->forceFill(['closed_at' => now()])->save();

        $this->sendText($number, self::SESSION_CLOSED_TEXT);
    }

    /**
     * Invoice passphrase confirmation is synchronous and deterministic: it
     * never goes through the AI loop nor waits for the debounce, exactly
     * like the web panel. Auth::onceUsingId is required because invoice
     * creation reads settings from auth()->user().
     */
    private function confirmPendingInvoice(AssistantConversation $session, User $owner, string $text): bool
    {
        if ($text === '') {
            return false;
        }

        $pending = $this->invoiceService->pending($session);

        if ($pending === null || trim($text) !== (string) $pending['passphrase']) {
            return false;
        }

        Auth::onceUsingId($owner->id);

        $session->messages()->create([
            'company_id' => $session->company_id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => $text,
        ]);

        $answer = $this->invoiceService->handleConfirmation($session, $text)
            ?? 'Não foi possível confirmar a fatura. Tente novamente.';

        $session->messages()->create([
            'company_id' => $session->company_id,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => $answer,
        ]);

        $session->touch();

        $this->sendText($session->whatsapp_number ?: '', $answer);

        return true;
    }

    private function activeSession(int $companyId, ?string $number): ?AssistantConversation
    {
        if ($number === null || $number === '') {
            return null;
        }

        return AssistantConversation::allTenants()
            ->where('company_id', $companyId)
            ->where('channel', AssistantConversation::CHANNEL_WHATSAPP)
            ->whereIn('whatsapp_number', $this->normalizer->candidates($number))
            ->whereNull('closed_at')
            ->orderByDesc('id')
            ->first();
    }

    private function openSession(int $companyId, User $owner, ?string $number, array $payload, array $messageData, string $text): AssistantConversation
    {
        $title = $text !== ''
            ? $text
            : ($this->mediaIngestor->filename($messageData) ?? 'Atendimento WhatsApp');

        return AssistantConversation::create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'title' => Str::limit($title, 60),
            'channel' => AssistantConversation::CHANNEL_WHATSAPP,
            'whatsapp_number' => $number,
            'whatsapp_jid' => (string) (data_get($messageData, 'key.remoteJid') ?: data_get($payload, 'sender') ?: ''),
        ]);
    }

    private function messageContent(string $text, string $mediaKind): string
    {
        if ($mediaKind === AssistantWhatsappMediaIngestor::KIND_AUDIO) {
            return self::AUDIO_PLACEHOLDER;
        }

        if ($text !== '') {
            return $text;
        }

        return AssistantChat::DEFAULT_ATTACHMENTS_INSTRUCTION;
    }

    /**
     * One ack per burst: when the message right before the one just created
     * is also a user message, the burst already got its ack.
     */
    private function maybeSendAck(AssistantConversation $session, AssistantMessage $message, string $number): void
    {
        if (! (bool) config('services.assistant.whatsapp.ack_enabled', true)) {
            return;
        }

        $previousRole = $session->messages()
            ->where('id', '<', $message->id)
            ->reorder('id', 'desc')
            ->value('role');

        if ($previousRole === AssistantMessage::ROLE_USER) {
            return;
        }

        $this->sendText($number, self::ACK_TEXT);
    }

    private function sendText(string $number, string $text): void
    {
        if ($number === '') {
            return;
        }

        try {
            $this->client->sendTextToNumber(
                (string) config('services.assistant.whatsapp.instance'),
                $number,
                $text,
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function helpText(): string
    {
        $endCommand = (string) config('services.assistant.whatsapp.end_command', '#fim');
        $helpCommand = (string) config('services.assistant.whatsapp.help_command', '#ajuda');

        return implode("\n", [
            '*Assistente de Dados do PeJota*',
            '',
            'Pergunte qualquer coisa sobre seus clientes, projetos, tarefas, sessões de trabalho e faturas. Também posso criar uma fatura — você confirma com uma palavra-passe.',
            '',
            'Formatos aceitos: texto, áudio, imagem (JPG/PNG/WebP), PDF, DOCX, XLSX, CSV e TXT.',
            '',
            'Comandos:',
            "- {$endCommand}: encerra o atendimento atual (a próxima mensagem abre um novo);",
            "- {$helpCommand}: mostra esta ajuda.",
        ]);
    }

    private function event(array $payload): string
    {
        return str((string) ($payload['event'] ?? ''))
            ->replace('.', '_')
            ->upper()
            ->toString();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function messages(array $payload): array
    {
        $data = $payload['data'] ?? [];

        if (is_array($data) && array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        return is_array($data) ? [$data] : [];
    }

    private function messageText(array $messageData): ?string
    {
        $candidates = [
            data_get($messageData, 'message.conversation'),
            data_get($messageData, 'message.extendedTextMessage.text'),
            data_get($messageData, 'message.imageMessage.caption'),
            data_get($messageData, 'message.documentMessage.caption'),
            data_get($messageData, 'message.documentWithCaptionMessage.message.documentMessage.caption'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function companyId(): int
    {
        $configured = config('services.evolution.default_company_id');

        if ($configured) {
            return (int) $configured;
        }

        return (int) Company::query()->value('id');
    }

    private function owner(int $companyId): ?User
    {
        return Company::query()->find($companyId)?->owner;
    }
}
