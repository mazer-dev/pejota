<?php

namespace App\Services\Planner;

use App\Enums\DailyPlanModeEnum;
use App\Enums\DailyPlanStatusEnum;
use App\Exceptions\DailyPlanParseException;
use App\Models\Company;
use App\Models\DailyPlan;
use App\Services\Ai\AiCliRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Generates the daily plan in a single AI call: the full panorama is
 * assembled deterministically by the context builder, the AI returns one
 * JSON plan and the parser validates every reference before persisting.
 *
 * This is the only AI call in the system that overrides the Codex model
 * and reasoning effort (services.planner.*): the plan of the day gets the
 * strongest configured model; everything else keeps the global default.
 */
class DailyPlanGenerator
{
    public function __construct(
        private readonly DailyPlanContextBuilder $contextBuilder,
        private readonly DailyPlanPromptBuilder $promptBuilder,
        private readonly DailyPlanResponseParser $parser,
        private readonly AiCliRunner $cliRunner,
    ) {}

    public function generate(Company $company, CarbonImmutable $date, DailyPlanModeEnum $mode): DailyPlan
    {
        $capacity = PlannerCapacity::forCompany($company);

        $attributes = [
            'mode' => $mode,
            'status' => DailyPlanStatusEnum::GENERATING,
            'capacity_minutes' => $mode === DailyPlanModeEnum::LIGHT ? 0 : $capacity->dayCapacityMinutes($date),
            'planned_minutes' => 0,
            'summary' => null,
            'warnings' => null,
            'failure_reason' => null,
            'generated_at' => null,
            'sent_at' => null,
        ];

        /**
         * Not updateOrCreate: plan_date has a date cast, so the stored value
         * ("Y-m-d H:i:s") never matches a plain "Y-m-d" in the lookup WHERE,
         * which would duplicate the row and hit the unique index instead.
         */
        $plan = DailyPlan::allTenants()
            ->where('company_id', $company->id)
            ->forDate($date)
            ->first();

        if ($plan) {
            $plan->update($attributes);
        } else {
            $plan = DailyPlan::allTenants()->create([
                ...$attributes,
                'company_id' => $company->id,
                'plan_date' => $date->toDateString(),
            ]);
        }

        $plan->items()->delete();

        try {
            $context = $this->contextBuilder->build($company, $date, $capacity);

            if ($mode === DailyPlanModeEnum::LIGHT) {
                $context = new DailyPlanContext(
                    text: $context->text,
                    capacityMinutes: 0,
                    validTaskIds: $context->validTaskIds,
                    validInvoiceIds: $context->validInvoiceIds,
                    validContractIds: $context->validContractIds,
                    validClientIds: $context->validClientIds,
                    validConversationIds: $context->validConversationIds,
                    truncated: $context->truncated,
                );
            }

            $prompt = $this->promptBuilder->build($context, $mode, $date);
            $response = $this->completeWithFallback($prompt);
            $parsed = $this->parser->parse($response, $context);
        } catch (Throwable $exception) {
            report($exception);

            $plan->update([
                'status' => DailyPlanStatusEnum::FAILED,
                'failure_reason' => $exception instanceof DailyPlanParseException || $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : __('Unexpected error while generating the plan.'),
            ]);

            return $plan->refresh();
        }

        DB::transaction(function () use ($plan, $parsed): void {
            foreach ($parsed->items as $position => $item) {
                $plan->items()->create([
                    ...$item,
                    'company_id' => $plan->company_id,
                    'position' => $position + 1,
                ]);
            }

            $plan->update([
                'status' => DailyPlanStatusEnum::READY,
                'summary' => $parsed->summary ?: null,
                'warnings' => $parsed->warnings ?: null,
                'planned_minutes' => $parsed->plannedMinutes(),
                'generated_at' => now(),
            ]);
        });

        return $plan->refresh()->load('items');
    }

    /**
     * First attempt uses the planner's model/effort overrides; if the CLI
     * rejects them (e.g. an effort level the installed Codex doesn't
     * support), a second attempt falls back to "high", and a final one
     * runs with the global defaults.
     */
    private function completeWithFallback(string $prompt): string
    {
        $options = array_filter([
            'codex_model' => config('services.planner.codex_model'),
            'codex_reasoning_effort' => config('services.planner.codex_reasoning_effort'),
            'timeout' => (int) config('services.planner.timeout', 600),
        ]);

        try {
            return $this->cliRunner->complete($prompt, options: $options);
        } catch (RuntimeException) {
            // Retried below with a safer effort level.
        }

        if (($options['codex_reasoning_effort'] ?? null) && $options['codex_reasoning_effort'] !== 'high') {
            try {
                return $this->cliRunner->complete($prompt, options: [
                    ...$options,
                    'codex_reasoning_effort' => 'high',
                ]);
            } catch (RuntimeException) {
                // Final fallback below.
            }
        }

        return $this->cliRunner->complete($prompt);
    }
}
