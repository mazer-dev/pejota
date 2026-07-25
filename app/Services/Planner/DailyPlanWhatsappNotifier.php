<?php

namespace App\Services\Planner;

use App\Enums\CompanySettingsEnum;
use App\Enums\DailyPlanItemTypeEnum;
use App\Helpers\PejotaHelper;
use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Services\Assistant\WhatsappMarkdownConverter;
use App\Services\Evolution\EvolutionApiClient;
use Throwable;

/**
 * Delivers the daily plan through the assistant's WhatsApp instance to
 * the numbers configured in services.planner.delivery_numbers. Sending is
 * best effort per number: a failure is reported but never breaks the
 * plan generation flow.
 */
class DailyPlanWhatsappNotifier
{
    private const CHUNK_CHARS = 4000;

    public function __construct(
        private readonly EvolutionApiClient $evolutionClient,
        private readonly WhatsappMarkdownConverter $markdownConverter,
    ) {}

    public function send(DailyPlan $plan, bool $force = false): bool
    {
        if (! (bool) config('services.assistant.whatsapp.enabled')) {
            return false;
        }

        $numbers = (array) config('services.planner.delivery_numbers', []);

        if ($numbers === [] || ! $plan->isReady()) {
            return false;
        }

        if ($plan->sent_at !== null && ! $force) {
            return false;
        }

        $deliveryEnabled = $plan->company->settings()->get(CompanySettingsEnum::PLANNER_WHATSAPP_DELIVERY->value);

        if ($deliveryEnabled === false) {
            return false;
        }

        $text = $this->markdownConverter->toWhatsapp($this->format($plan));
        $instance = (string) config('services.assistant.whatsapp.instance');
        $delivered = false;

        foreach ($numbers as $number) {
            try {
                foreach ($this->markdownConverter->chunk($text, self::CHUNK_CHARS) as $chunk) {
                    $this->evolutionClient->sendTextToNumber($instance, (string) $number, $chunk);
                }

                $delivered = true;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if ($delivered) {
            $plan->update(['sent_at' => now()]);
        }

        return $delivered;
    }

    private function format(DailyPlan $plan): string
    {
        $dateFormat = PejotaHelper::getUserDateFormatOrDefault('d/m/Y');
        $lines = [];

        $lines[] = '**Plano do dia - '.$plan->plan_date->format($dateFormat).'**'
            .($plan->isLight() ? ' (dia de folga)' : '');

        if (filled($plan->summary)) {
            $lines[] = '';
            $lines[] = trim((string) $plan->summary);
        }

        $items = $plan->items;

        if ($items->isEmpty()) {
            if (blank($plan->summary)) {
                $lines[] = '';
                $lines[] = 'Nada urgente hoje. Bom descanso!';
            }
        } else {
            $lines[] = '';

            foreach ($items->values() as $index => $item) {
                $lines[] = $this->formatItem($index + 1, $item);
            }

            $lines[] = '';
            $lines[] = 'Total planejado: '.PejotaHelper::formatDuration((int) $plan->planned_minutes)
                .($plan->capacity_minutes > 0 ? ' de '.PejotaHelper::formatDuration((int) $plan->capacity_minutes).' disponíveis' : '');
        }

        foreach ((array) $plan->warnings as $warning) {
            $lines[] = '';
            $lines[] = '⚠️ '.$warning;
        }

        return implode("\n", $lines);
    }

    private function formatItem(int $number, DailyPlanItem $item): string
    {
        $line = "{$number}. **{$item->title}** (".PejotaHelper::formatDuration((int) $item->estimated_minutes).')';

        if (filled($item->reason)) {
            $line .= "\n   ".$item->reason;
        }

        if ($item->type === DailyPlanItemTypeEnum::FOLLOW_UP && filled($item->suggested_message)) {
            $line .= "\n   Mensagem sugerida: \"".$item->suggested_message.'"';
        }

        return $line;
    }
}
