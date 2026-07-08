<?php

namespace App\Services\Ai\Context;

use App\Enums\InvoiceStatusEnum;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Support\Carbon;
use NumberFormatter;

/**
 * Summarizes a client's open/overdue invoices and the most recently paid
 * ones. Subscriptions are intentionally not included here: the
 * Subscription model has no relationship to a Client (it tracks the
 * company's own paid tools), so there is nothing per-client to surface.
 */
class InvoicesContextSection
{
    public function build(?Client $client, ?Project $project = null): ?string
    {
        if (! $client) {
            return null;
        }

        $today = Carbon::today(PejotaHelper::getUserTimeZoneOrDefault());
        $dateFormat = PejotaHelper::getUserDateFormatOrDefault();
        $formatter = NumberFormatter::create(PejotaHelper::getUserLocateOrDefault(), NumberFormatter::CURRENCY);

        $base = Invoice::query()
            ->where('client_id', $client->id)
            ->when($project, fn ($query) => $query->where('project_id', $project->id));

        $pending = (clone $base)->pending()->orderBy('due_date')->get();
        $paid = (clone $base)
            ->where('status', InvoiceStatusEnum::PAID)
            ->orderByDesc('payment_date')
            ->limit(3)
            ->get();

        $lines = [];

        $overdue = $pending->filter(fn (Invoice $invoice): bool => $invoice->due_date && $invoice->due_date->lt($today));
        if ($overdue->isNotEmpty()) {
            $lines[] = 'Vencidas:';
            foreach ($overdue as $invoice) {
                $daysLate = (int) $invoice->due_date->diffInDays($today);
                $amount = $formatter->formatCurrency((float) $invoice->total, $invoice->currency ?? PejotaHelper::getUserCurrencyOrDefault());
                $lines[] = "- #{$invoice->number} {$invoice->title} - {$amount} - venceu em {$invoice->due_date->format($dateFormat)}, {$daysLate} dia(s) de atraso";
            }
        }

        $open = $pending->filter(fn (Invoice $invoice): bool => ! $invoice->due_date || $invoice->due_date->gte($today));
        if ($open->isNotEmpty()) {
            $lines[] = 'Em aberto:';
            foreach ($open as $invoice) {
                $amount = $formatter->formatCurrency((float) $invoice->total, $invoice->currency ?? PejotaHelper::getUserCurrencyOrDefault());
                $due = $invoice->due_date ? "vence em {$invoice->due_date->format($dateFormat)}" : 'sem data de vencimento';
                $lines[] = "- #{$invoice->number} {$invoice->title} - {$amount} - {$due}";
            }
        }

        if ($paid->isNotEmpty()) {
            $lines[] = 'Últimas pagas:';
            foreach ($paid as $invoice) {
                $amount = $formatter->formatCurrency((float) $invoice->total, $invoice->currency ?? PejotaHelper::getUserCurrencyOrDefault());
                $date = $invoice->payment_date?->format($dateFormat) ?? '-';
                $lines[] = "- #{$invoice->number} {$invoice->title} - {$amount} - paga em {$date}";
            }
        }

        if ($lines === []) {
            return null;
        }

        return "Faturas:\n".implode("\n", $lines);
    }
}
