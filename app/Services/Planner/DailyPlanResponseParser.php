<?php

namespace App\Services\Planner;

use App\Enums\DailyPlanItemTypeEnum;
use App\Exceptions\DailyPlanParseException;
use App\Services\Ai\Support\AiJsonExtractor;
use Illuminate\Support\Str;

/**
 * Validates the AI's plan deterministically: unknown item types and ids
 * that were never shown in the context are stripped (anti-hallucination
 * and anti cross-tenant), estimates are clamped and the list is cut at
 * the day's capacity. The AI proposes; this class decides what persists.
 */
class DailyPlanResponseParser
{
    private const MIN_ITEM_MINUTES = 5;

    private const MAX_ITEM_MINUTES = 480;

    private const MAX_SUGGESTED_MESSAGE_CHARS = 600;

    /**
     * Follow-ups and habits may exceed the capacity cut by this factor:
     * they are short and skipping them defeats the plan's purpose.
     */
    private const CAPACITY_GRACE_FACTOR = 1.2;

    public function __construct(private readonly AiJsonExtractor $jsonExtractor) {}

    public function parse(string $response, DailyPlanContext $context): ParsedDailyPlan
    {
        $decoded = $this->jsonExtractor->extract($response);

        if ($decoded === null || ! is_array($decoded['items'] ?? null)) {
            throw new DailyPlanParseException('A resposta da IA não contém um JSON de plano utilizável.');
        }

        $warnings = collect($decoded['warnings'] ?? [])
            ->filter(fn ($warning): bool => is_string($warning) && trim($warning) !== '')
            ->map(fn (string $warning): string => Str::limit(trim($warning), 300))
            ->values()
            ->all();

        $items = [];
        $followedUpClients = [];

        foreach ($decoded['items'] as $raw) {
            $item = $this->normalizeItem($raw, $context);

            if ($item === null) {
                continue;
            }

            if ($item['type'] === DailyPlanItemTypeEnum::FOLLOW_UP->value && $item['client_id'] !== null) {
                if (in_array($item['client_id'], $followedUpClients, true)) {
                    continue;
                }

                $followedUpClients[] = $item['client_id'];
            }

            $items[] = $item;
        }

        [$items, $capacityWarning] = $this->cutAtCapacity($items, $context->capacityMinutes);

        if ($capacityWarning !== null) {
            $warnings[] = $capacityWarning;
        }

        return new ParsedDailyPlan(
            summary: Str::limit(trim((string) ($decoded['summary'] ?? '')), 1000),
            items: $items,
            warnings: $warnings,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeItem(mixed $raw, DailyPlanContext $context): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $type = DailyPlanItemTypeEnum::tryFrom((string) ($raw['type'] ?? ''));
        $title = trim((string) ($raw['title'] ?? ''));

        if ($type === null || $title === '') {
            return null;
        }

        $minutes = (int) ($raw['estimated_minutes'] ?? 0);
        $minutes = max(self::MIN_ITEM_MINUTES, min(self::MAX_ITEM_MINUTES, $minutes));

        $taskId = $this->whitelistedId($raw['task_id'] ?? null, $context->validTaskIds);
        $invoiceId = $this->whitelistedId($raw['invoice_id'] ?? null, $context->validInvoiceIds);
        $contractId = $this->whitelistedId($raw['contract_id'] ?? null, $context->validContractIds);
        $clientId = $this->whitelistedId($raw['client_id'] ?? null, $context->validClientIds);
        $conversationId = $this->whitelistedId($raw['whatsapp_conversation_id'] ?? null, $context->validConversationIds);

        if ($type === DailyPlanItemTypeEnum::TASK && $taskId === null) {
            return null;
        }

        if ($type === DailyPlanItemTypeEnum::INVOICE && $invoiceId === null && $clientId === null) {
            return null;
        }

        if ($type === DailyPlanItemTypeEnum::HABIT && $taskId === null) {
            return null;
        }

        $suggestedMessage = null;
        if ($type === DailyPlanItemTypeEnum::FOLLOW_UP) {
            $suggestedMessage = trim((string) ($raw['suggested_message'] ?? ''));
            $suggestedMessage = $suggestedMessage === '' ? null : Str::limit($suggestedMessage, self::MAX_SUGGESTED_MESSAGE_CHARS);
        }

        return [
            'type' => $type->value,
            'title' => Str::limit($title, 255),
            'reason' => Str::limit(trim((string) ($raw['reason'] ?? '')), 500) ?: null,
            'estimated_minutes' => $minutes,
            'task_id' => $taskId,
            'invoice_id' => $invoiceId,
            'contract_id' => $contractId,
            'client_id' => $clientId,
            'whatsapp_conversation_id' => $conversationId,
            'suggested_message' => $suggestedMessage,
        ];
    }

    /**
     * @param  array<int, int>  $whitelist
     */
    private function whitelistedId(mixed $value, array $whitelist): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return in_array($id, $whitelist, true) ? $id : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{0: array<int, array<string, mixed>>, 1: string|null}
     */
    private function cutAtCapacity(array $items, int $capacityMinutes): array
    {
        if ($capacityMinutes <= 0) {
            return [$items, null];
        }

        $graceTypes = [DailyPlanItemTypeEnum::FOLLOW_UP->value, DailyPlanItemTypeEnum::HABIT->value];
        $hardLimit = (int) round($capacityMinutes * self::CAPACITY_GRACE_FACTOR);

        $kept = [];
        $sum = 0;
        $dropped = 0;

        foreach ($items as $item) {
            $withItem = $sum + (int) $item['estimated_minutes'];

            $limit = in_array($item['type'], $graceTypes, true) ? $hardLimit : $capacityMinutes;

            if ($withItem > $limit) {
                $dropped++;

                continue;
            }

            $kept[] = $item;
            $sum = $withItem;
        }

        $warning = $dropped > 0
            ? __(':count item(s) sugeridos pela IA não couberam na capacidade de hoje e foram removidos.', ['count' => $dropped])
            : null;

        return [$kept, $warning];
    }
}
