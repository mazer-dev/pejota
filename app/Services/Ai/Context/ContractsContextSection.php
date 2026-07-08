<?php

namespace App\Services\Ai\Context;

use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Support\Carbon;

/**
 * Summarizes a client's currently active contracts (title + validity period).
 */
class ContractsContextSection
{
    public function build(?Client $client): ?string
    {
        if (! $client) {
            return null;
        }

        $dateFormat = PejotaHelper::getUserDateFormatOrDefault();
        $today = now(PejotaHelper::getUserTimeZoneOrDefault())->toDateString();

        $contracts = Contract::query()
            ->where('client_id', $client->id)
            ->where('start_at', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('end_at')->orWhere('end_at', '>=', $today);
            })
            ->orderByDesc('start_at')
            ->get();

        if ($contracts->isEmpty()) {
            return null;
        }

        $lines = $contracts->map(function (Contract $contract) use ($dateFormat): string {
            $start = $contract->start_at ? Carbon::parse($contract->start_at)->format($dateFormat) : '-';
            $end = $contract->end_at ? Carbon::parse($contract->end_at)->format($dateFormat) : 'indeterminado';

            return "- {$contract->title} - vigência: {$start} a {$end}";
        });

        return "Contratos vigentes:\n".$lines->implode("\n");
    }
}
