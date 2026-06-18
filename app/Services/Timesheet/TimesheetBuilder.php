<?php

namespace App\Services\Timesheet;

use App\Enums\TimesheetDetailLevel;
use App\Enums\TimesheetGrouping;
use App\Models\Client;
use App\Models\Task;
use App\Models\WorkSession;
use Illuminate\Support\Collection;

class TimesheetBuilder
{
    /**
     * @var array<int, Task|null>
     */
    private array $taskCache = [];

    public function build(TimesheetRequest $request): TimesheetData
    {
        $client = Client::findOrFail($request->clientId);
        $currency = $request->currency;

        $sessions = WorkSession::query()
            ->where('client_id', $request->clientId)
            ->whereBetween('start', [$request->from, $request->to])
            ->when($request->billableOnly, fn ($query) => $query->where('billable', true))
            ->with(['task', 'project', 'client'])
            ->orderBy('start')
            ->get();

        $groups = $sessions
            ->groupBy(fn (WorkSession $session) => $this->groupKey($session, $request))
            ->map(fn (Collection $groupSessions, string $label) => new TimesheetGroup(
                label: $label,
                entries: $this->buildEntries($groupSessions, $request),
                subtotalMinutes: (int) $groupSessions->sum('duration'),
                subtotalValue: (float) $groupSessions->sum(fn (WorkSession $s) => (float) $s->value),
            ))
            ->values();

        return new TimesheetData(
            client: $client,
            from: $request->from,
            to: $request->to,
            currency: $currency,
            layoutKey: $request->layoutKey,
            includeValue: $request->includeValue,
            groups: $groups,
            grandTotalMinutes: (int) $sessions->sum('duration'),
            grandTotalValue: (float) $sessions->sum(fn (WorkSession $s) => (float) $s->value),
        );
    }

    private function groupKey(WorkSession $session, TimesheetRequest $request): string
    {
        $local = $session->start->copy()->setTimezone($request->timezone);

        return match ($request->grouping) {
            TimesheetGrouping::Project => $session->project?->name ?? __('No project'),
            TimesheetGrouping::Task => $session->task?->title ?? __('No task'),
            TimesheetGrouping::Day => $local->format('Y-m-d'),
            TimesheetGrouping::Week => __('Week of :date', ['date' => $local->copy()->startOfWeek()->format('Y-m-d')]),
            TimesheetGrouping::Month => $local->format('Y-m'),
            TimesheetGrouping::None => __('Total'),
        };
    }

    /**
     * @param  Collection<int, WorkSession>  $sessions
     * @return Collection<int, TimesheetEntry>
     */
    private function buildEntries(Collection $sessions, TimesheetRequest $request): Collection
    {
        return match ($request->detailLevel) {
            TimesheetDetailLevel::GroupSummary => collect(),
            TimesheetDetailLevel::Detailed => $sessions->map(fn (WorkSession $session) => new TimesheetEntry(
                date: $session->start->copy()->setTimezone($request->timezone)->toImmutable(),
                taskTitle: $session->task?->title,
                projectName: $session->project?->name,
                description: $session->title,
                minutes: (int) $session->duration,
                rate: (float) $session->rate,
                value: (float) $session->value,
            ))->values(),
            TimesheetDetailLevel::ParentTaskRollup => $this->rollupEntries($sessions, $request),
        };
    }

    /**
     * @param  Collection<int, WorkSession>  $sessions
     * @return Collection<int, TimesheetEntry>
     */
    private function rollupEntries(Collection $sessions, TimesheetRequest $request): Collection
    {
        return $sessions
            ->groupBy(fn (WorkSession $session) => $this->rootTask($session->task)?->id ?? 0)
            ->map(function (Collection $bucket) use ($request) {
                $root = $this->rootTask($bucket->first()->task);
                $first = $bucket->sortBy('start')->first();

                return new TimesheetEntry(
                    date: $first->start->copy()->setTimezone($request->timezone)->toImmutable(),
                    taskTitle: $root?->title ?? __('No task'),
                    projectName: $bucket->first()->project?->name,
                    description: $root?->title ?? __('No task'),
                    minutes: (int) $bucket->sum('duration'),
                    rate: null,
                    value: (float) $bucket->sum(fn (WorkSession $s) => (float) $s->value),
                );
            })
            ->values();
    }

    private function rootTask(?Task $task): ?Task
    {
        if (! $task) {
            return null;
        }

        $current = $task;
        $guard = 0;

        while ($current->parent_id !== null && $guard < 50) {
            $parent = $this->taskCache[$current->parent_id] ??= Task::find($current->parent_id);

            if (! $parent) {
                break;
            }

            $current = $parent;
            $guard++;
        }

        return $current;
    }
}
