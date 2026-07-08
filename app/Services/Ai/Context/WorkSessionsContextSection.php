<?php

namespace App\Services\Ai\Context;

use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Project;
use App\Models\WorkSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Summarizes work sessions logged in the last 14 days for a client and/or
 * project: date, duration, and a short title/description.
 */
class WorkSessionsContextSection
{
    private const DAYS = 14;

    public function build(?Client $client = null, ?Project $project = null): ?string
    {
        if (! $client && ! $project) {
            return null;
        }

        $timezone = PejotaHelper::getUserTimeZoneOrDefault();
        $since = Carbon::now($timezone)->subDays(self::DAYS);
        $dateFormat = PejotaHelper::getUserDateFormatOrDefault();

        $sessions = WorkSession::query()
            ->where(function (Builder $query) use ($client, $project): void {
                if ($client) {
                    $query->orWhere('client_id', $client->id);
                }

                if ($project) {
                    $query->orWhere('project_id', $project->id);
                }
            })
            ->where('start', '>=', $since)
            ->orderByDesc('start')
            ->get();

        if ($sessions->isEmpty()) {
            return null;
        }

        $lines = $sessions->map(function (WorkSession $session) use ($dateFormat, $timezone): string {
            $date = $session->start?->clone()->timezone($timezone)->format($dateFormat) ?? '-';
            $duration = PejotaHelper::formatDuration($session->duration);
            $label = $session->title ?: $session->description;
            $label = $label ? ' - '.Str::limit(strip_tags((string) $label), 80) : '';

            return "- {$date} ({$duration}){$label}";
        });

        return 'Sessões de trabalho (últimos '.self::DAYS." dias):\n".$lines->implode("\n");
    }
}
