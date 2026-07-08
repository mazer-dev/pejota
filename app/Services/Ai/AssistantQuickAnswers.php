<?php

namespace App\Services\Ai;

use App\Helpers\PejotaHelper;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\WorkSession;
use Illuminate\Support\Carbon;
use NumberFormatter;

/**
 * Deterministic, AI-free answers for the assistant's quick chips. Each
 * method runs plain Eloquent queries (tenant-scoped by BelongsToTenants in
 * the authenticated request) and formats the answer directly in PHP, so
 * the response is instantaneous and never touches the AI CLI.
 */
class AssistantQuickAnswers
{
    public const CHIP_TODAY = 'today';

    public const CHIP_OVERDUE_INVOICES = 'overdue_invoices';

    public const CHIP_WEEK_SUMMARY = 'week_summary';

    /**
     * @return array<string, string> chip key => label
     */
    public static function chips(): array
    {
        return [
            self::CHIP_TODAY => __('What is on for today?'),
            self::CHIP_OVERDUE_INVOICES => __('Overdue invoices'),
            self::CHIP_WEEK_SUMMARY => __('Week summary'),
        ];
    }

    public function answer(string $chip): ?string
    {
        return match ($chip) {
            self::CHIP_TODAY => $this->today(),
            self::CHIP_OVERDUE_INVOICES => $this->overdueInvoices(),
            self::CHIP_WEEK_SUMMARY => $this->weekSummary(),
            default => null,
        };
    }

    private function today(): string
    {
        $today = Carbon::today(PejotaHelper::getUserTimeZoneOrDefault());
        $dateFormat = PejotaHelper::getUserDateFormatOrDefault();

        $tasks = Task::query()
            ->with('client')
            ->opened()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->toDateString())
            ->orderBy('due_date')
            ->get();

        if ($tasks->isEmpty()) {
            return __('No open tasks due today or overdue. Enjoy the clear day!');
        }

        $lines = $tasks->map(function (Task $task) use ($today, $dateFormat): string {
            $client = $task->client ? " [{$task->client->name}]" : '';

            if ($task->due_date->lt($today)) {
                $days = (int) $task->due_date->diffInDays($today);

                return "- {$task->title}{$client} - ".__('overdue by :days day(s)', ['days' => $days]);
            }

            return "- {$task->title}{$client} - ".__('due today').' ('.$task->due_date->format($dateFormat).')';
        });

        return __('Open tasks due today or overdue (:count):', ['count' => $tasks->count()])."\n".$lines->implode("\n");
    }

    private function overdueInvoices(): string
    {
        $today = Carbon::today(PejotaHelper::getUserTimeZoneOrDefault());
        $formatter = NumberFormatter::create(PejotaHelper::getUserLocateOrDefault(), NumberFormatter::CURRENCY);

        $invoices = Invoice::query()
            ->with('client')
            ->overdue()
            ->orderBy('due_date')
            ->get();

        if ($invoices->isEmpty()) {
            return __('No overdue invoices. All caught up!');
        }

        $lines = $invoices->map(function (Invoice $invoice) use ($today, $formatter): string {
            $client = $invoice->client?->name ?? '-';
            $amount = $formatter->formatCurrency((float) $invoice->total, $invoice->currency ?? PejotaHelper::getUserCurrencyOrDefault());
            $days = (int) $invoice->due_date->diffInDays($today);

            return "- #{$invoice->number} {$invoice->title} [{$client}] - {$amount} - ".__('overdue by :days day(s)', ['days' => $days]);
        });

        return __('Overdue invoices (:count):', ['count' => $invoices->count()])."\n".$lines->implode("\n");
    }

    private function weekSummary(): string
    {
        $timezone = PejotaHelper::getUserTimeZoneOrDefault();
        $since = Carbon::now($timezone)->subDays(7);

        $sessions = WorkSession::query()
            ->with('client')
            ->where('start', '>=', $since)
            ->get();

        if ($sessions->isEmpty()) {
            return __('No work sessions in the last 7 days.');
        }

        $total = PejotaHelper::formatDuration((int) $sessions->sum('duration'));

        $byClient = $sessions
            ->groupBy(fn (WorkSession $session): string => $session->client?->name ?? __('No client'))
            ->map(fn ($group, string $clientName): string => "- {$clientName}: ".PejotaHelper::formatDuration((int) $group->sum('duration')).' ('.$group->count().' '.__('sessions').')')
            ->values();

        return __('Last 7 days: :count session(s), :total total.', ['count' => $sessions->count(), 'total' => $total])
            ."\n".$byClient->implode("\n");
    }
}
