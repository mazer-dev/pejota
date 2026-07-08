<?php

namespace App\Services\Ai\Context;

use App\Models\Client;
use App\Models\ClientAiAnalysis;

/**
 * Surfaces the most recent persisted ClientAiAnalysis for a client, flagged
 * with its age so the model knows the facts gathered elsewhere in the
 * context are more up to date than this analysis.
 */
class ClientAnalysisContextSection
{
    public function build(?Client $client): ?string
    {
        if (! $client) {
            return null;
        }

        /** @var ClientAiAnalysis|null $analysis */
        $analysis = $client->relationLoaded('latestAnalysis')
            ? $client->latestAnalysis
            : $client->latestAnalysis()->first();

        if (! $analysis) {
            return null;
        }

        $daysAgo = (int) $analysis->created_at->diffInDays(now());

        return "Análise do relacionamento gerada há {$daysAgo} dia(s) (os fatos acima são mais recentes que ela):\n{$analysis->content}";
    }
}
