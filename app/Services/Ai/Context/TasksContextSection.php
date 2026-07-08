<?php

namespace App\Services\Ai\Context;

use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Summarizes a client's (and/or project's) open tasks grouped by phase,
 * highlighting overdue items and upcoming due dates, plus the most
 * recently closed tasks.
 */
class TasksContextSection
{
    public function build(?Client $client = null, ?Project $project = null): ?string
    {
        if (! $client && ! $project) {
            return null;
        }

        $today = Carbon::today(PejotaHelper::getUserTimeZoneOrDefault());
        $dateFormat = PejotaHelper::getUserDateFormatOrDefault();

        $opened = $this->baseQuery($client, $project)
            ->with('status')
            ->opened()
            ->orderBy('due_date')
            ->get();

        $closed = $this->baseQuery($client, $project)
            ->with('status')
            ->closed()
            ->orderByDesc('actual_end')
            ->limit(5)
            ->get();

        $lines = [];

        if ($opened->isNotEmpty()) {
            foreach ($opened->groupBy(fn (Task $task): string => $task->status?->name ?? __('No status')) as $phaseLabel => $tasks) {
                $lines[] = "{$phaseLabel} ({$tasks->count()}):";

                foreach ($tasks as $task) {
                    $lines[] = '- '.$this->taskLine($task, $today, $dateFormat);
                }
            }
        }

        $upcoming = $opened
            ->filter(fn (Task $task): bool => $task->due_date && $task->due_date->gte($today))
            ->sortBy('due_date')
            ->take(5);

        if ($upcoming->isNotEmpty()) {
            $lines[] = 'Próximos vencimentos:';
            foreach ($upcoming as $task) {
                $lines[] = "- {$task->title}: {$task->due_date->format($dateFormat)}";
            }
        }

        if ($closed->isNotEmpty()) {
            $lines[] = 'Últimas concluídas:';
            foreach ($closed as $task) {
                $date = $task->actual_end?->format($dateFormat) ?? '-';
                $lines[] = "- {$task->title} - concluída em {$date}";
            }
        }

        if ($lines === []) {
            return null;
        }

        return "Tarefas:\n".implode("\n", $lines);
    }

    private function taskLine(Task $task, Carbon $today, string $dateFormat): string
    {
        if (! $task->due_date) {
            return "{$task->title} (sem data de vencimento)";
        }

        if ($task->due_date->lt($today)) {
            $daysLate = (int) $task->due_date->diffInDays($today);

            return "{$task->title} - ATRASADA há {$daysLate} dia(s) (venceu em {$task->due_date->format($dateFormat)})";
        }

        return "{$task->title} - vence em {$task->due_date->format($dateFormat)}";
    }

    private function baseQuery(?Client $client, ?Project $project): Builder
    {
        return Task::query()->where(function (Builder $query) use ($client, $project): void {
            if ($client) {
                $query->orWhere('client_id', $client->id);
            }

            if ($project) {
                $query->orWhere('project_id', $project->id);
            }
        });
    }
}
