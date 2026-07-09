<?php

namespace App\Services\Ai;

use App\Enums\WhatsappSuggestionStatusEnum;
use App\Enums\WhatsappSuggestionTypeEnum;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSuggestion;
use App\Services\Ai\Context\ConversationHistoryRenderer;
use App\Services\Ai\Context\PromptGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads the new messages of a WhatsApp conversation and turns whatever is
 * clearly actionable into pending WhatsappSuggestion records (a Task or a
 * Note to be created). It never creates the Task/Note itself: Luiz reviews
 * each suggestion on the conversation screen and accepts or dismisses it.
 */
class WhatsappSuggestionService
{
    public function __construct(
        private readonly ConversationContextBuilder $contextBuilder,
        private readonly ConversationHistoryRenderer $historyRenderer,
        private readonly AiCliRunner $cliRunner,
    ) {}

    /**
     * Analyzes the given new messages and persists one pending suggestion per
     * actionable item found, skipping titles that already exist as pending or
     * accepted suggestions in the same conversation.
     *
     * @param  Collection<int, WhatsappMessage>  $newMessages
     * @return Collection<int, WhatsappSuggestion>
     */
    public function analyze(WhatsappConversation $conversation, Collection $newMessages, ?WhatsappMessage $anchorMessage = null): Collection
    {
        if ($newMessages->isEmpty()) {
            return collect();
        }

        $response = $this->cliRunner->complete($this->prompt($conversation, $newMessages));

        $items = $this->parseSuggestions($response);

        return $this->storeSuggestions($conversation, $items, $anchorMessage);
    }

    /**
     * @param  array<int, array{type: string, title: string, content: string}>  $items
     * @return Collection<int, WhatsappSuggestion>
     */
    private function storeSuggestions(WhatsappConversation $conversation, array $items, ?WhatsappMessage $anchorMessage): Collection
    {
        $existingTitles = WhatsappSuggestion::allTenants()
            ->where('company_id', $conversation->company_id)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->whereIn('status', [WhatsappSuggestionStatusEnum::Pending, WhatsappSuggestionStatusEnum::Accepted])
            ->pluck('title')
            ->map(fn (string $title): string => $this->normalizeTitle($title))
            ->all();

        $created = collect();

        foreach ($items as $item) {
            $normalized = $this->normalizeTitle($item['title']);

            if ($normalized === '' || in_array($normalized, $existingTitles, true)) {
                continue;
            }

            $existingTitles[] = $normalized;

            $created->push(WhatsappSuggestion::create([
                'company_id' => $conversation->company_id,
                'whatsapp_conversation_id' => $conversation->id,
                'whatsapp_message_id' => $anchorMessage?->id,
                'client_id' => $conversation->client_id,
                'project_id' => $conversation->project_id,
                'type' => $item['type'],
                'title' => $item['title'],
                'content' => $item['content'],
                'status' => WhatsappSuggestionStatusEnum::Pending,
            ]));
        }

        return $created;
    }

    /**
     * @param  Collection<int, WhatsappMessage>  $newMessages
     */
    private function prompt(WhatsappConversation $conversation, Collection $newMessages): string
    {
        $conversation->loadMissing(['client', 'project', 'messages.attachments']);
        $newMessages->each(fn (WhatsappMessage $message) => $message->loadMissing('attachments'));

        $history = $this->historyRenderer->render($conversation->messages, 'Luiz', 30);
        $context = $this->contextBuilder->build($conversation->client, $conversation->project, $history !== '' ? $history : null);
        $newHistory = $this->historyRenderer->render($newMessages, 'Luiz');

        $instructions = implode("\n", [
            'Você analisa mensagens recebidas de clientes no WhatsApp do Luiz/Pejota e sugere itens acionáveis para ele registrar no sistema.',
            'Tipos de sugestão possíveis:',
            '- "note": informação que vale guardar como anotação (ex.: credencial ou dado de acesso enviado pelo cliente, decisão tomada, preferência importante).',
            '- "task": trabalho novo que o cliente pediu ou confirmou (ex.: escopo novo, ajuste, correção, entrega combinada).',
            'Sugira apenas o que estiver claramente acionável nas mensagens novas; conversa trivial, saudações e assuntos já resolvidos não geram sugestão.',
            'Você nunca cria nada sozinho: o Luiz revisa cada sugestão e decide aceitar ou descartar.',
            'Escreva "title" curto e objetivo e "content" com os detalhes relevantes, em português do Brasil.',
            PromptGuard::instruction(),
            'Responda somente com JSON válido: um array de objetos no formato {"type": "task"|"note", "title": "...", "content": "..."}.',
            'Se não houver nada claramente acionável, responda exatamente [].',
        ]);

        return implode("\n\n", [
            $instructions,
            "Contexto disponível:\n".PromptGuard::wrap($context),
            "Mensagens novas desde a última análise (analise apenas estas):\n".PromptGuard::wrap($newHistory),
            'Gere as sugestões agora.',
        ]);
    }

    /**
     * Parses the AI response tolerating code fences and prose around the
     * JSON, and discarding items without a valid type or title.
     *
     * @return array<int, array{type: string, title: string, content: string}>
     */
    public function parseSuggestions(string $response): array
    {
        $json = $this->stripCodeFences($response);

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            $first = $this->firstJsonArray($json);
            $decoded = $first !== null ? json_decode($first, true) : null;
        }

        if (is_array($decoded) && ! array_is_list($decoded)) {
            $decoded = is_array($decoded['suggestions'] ?? null) ? $decoded['suggestions'] : [$decoded];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $items = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = is_string($item['type'] ?? null) ? strtolower(trim($item['type'])) : null;
            $title = is_string($item['title'] ?? null) ? trim($item['title']) : '';
            $content = is_string($item['content'] ?? null) ? trim($item['content']) : '';

            if ($title === '' || WhatsappSuggestionTypeEnum::tryFrom((string) $type) === null) {
                continue;
            }

            $items[] = [
                'type' => $type,
                'title' => Str::limit($title, 255, ''),
                'content' => $content !== '' ? $content : $title,
            ];
        }

        return $items;
    }

    /**
     * Titles are compared loosely (case, spacing and punctuation ignored) so
     * the AI re-suggesting "Criar cadência de e-mails!" does not duplicate an
     * existing "criar cadência de e-mails" suggestion.
     */
    private function normalizeTitle(string $title): string
    {
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($title)) ?? '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
    }

    private function stripCodeFences(string $response): string
    {
        $trimmed = trim($response);

        if (Str::startsWith($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/```\s*$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }

    /**
     * Extracts the first balanced [...] array from the text, so a response
     * with prose around the JSON still yields the suggestions.
     */
    private function firstJsonArray(string $text): ?string
    {
        $start = strpos($text, '[');

        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $start, $length = strlen($text); $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;

                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
