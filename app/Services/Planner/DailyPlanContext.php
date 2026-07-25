<?php

namespace App\Services\Planner;

/**
 * Deterministic snapshot of the company's situation handed to the AI in a
 * single prompt. The id whitelists collected while building the text are
 * the anti-hallucination source of truth: the response parser drops or
 * strips any reference the context never mentioned.
 */
final class DailyPlanContext
{
    /**
     * @param  array<int, int>  $validTaskIds
     * @param  array<int, int>  $validInvoiceIds
     * @param  array<int, int>  $validContractIds
     * @param  array<int, int>  $validClientIds
     * @param  array<int, int>  $validConversationIds
     */
    public function __construct(
        public readonly string $text,
        public readonly int $capacityMinutes,
        public readonly array $validTaskIds = [],
        public readonly array $validInvoiceIds = [],
        public readonly array $validContractIds = [],
        public readonly array $validClientIds = [],
        public readonly array $validConversationIds = [],
        public readonly bool $truncated = false,
    ) {}
}
