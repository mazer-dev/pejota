<?php

namespace App\Services;

use App\Enums\RecurrenceAnchorFieldEnum;
use App\Enums\RecurrenceFrequencyEnum;
use App\Enums\RecurrenceGenerationModeEnum;
use App\Enums\RecurrenceStopTypeEnum;
use App\Models\Status;
use App\Models\Task;
use App\Models\TaskRecurrence;
use Illuminate\Support\Carbon;

class RecurrenceService
{
    /**
     * Safety cap on how many occurrences a single run may generate for one rule,
     * so a long-dormant rule (missed cron, restored backup) drains over several
     * runs instead of flooding hundreds of tasks at once. The remainder stays
     * due (next_run_date in the past) and is picked up on the next run.
     */
    private const MAX_OCCURRENCES_PER_RUN = 366;

    public function nextDate(Carbon $from, RecurrenceFrequencyEnum $frequency, int $interval): Carbon
    {
        return match ($frequency) {
            RecurrenceFrequencyEnum::Weekly => $from->copy()->addWeeks($interval),
            RecurrenceFrequencyEnum::Monthly => $from->copy()->addMonthsNoOverflow($interval),
            RecurrenceFrequencyEnum::Yearly => $from->copy()->addYearsNoOverflow($interval),
        };
    }

    public function makeOccurrence(TaskRecurrence $recurrence, Carbon $date): Task
    {
        $template = Task::withoutGlobalScopes()->findOrFail($recurrence->task_id);

        $occurrence = $template->replicate();
        $occurrence->is_recurrence_template = false;
        $occurrence->recurrence_id = $recurrence->id;
        $occurrence->company_id = $recurrence->company_id;
        $occurrence->planned_start = null;
        $occurrence->planned_end = null;
        $occurrence->actual_start = null;
        $occurrence->actual_end = null;
        $occurrence->due_date = null;
        $occurrence->is_continuous = false;
        $occurrence->continuous_mode = null;
        $occurrence->status_id = Status::withoutGlobalScopes()
            ->where('company_id', $recurrence->company_id)
            ->orderBy('sort_order')
            ->first()?->id;

        $this->applyAnchorDates($occurrence, $recurrence, $date);

        $occurrence->save();

        return $occurrence;
    }

    private function applyAnchorDates(Task $occurrence, TaskRecurrence $recurrence, Carbon $date): void
    {
        $anchor = $recurrence->anchor_field;

        if ($anchor === RecurrenceAnchorFieldEnum::DueDate || $anchor === RecurrenceAnchorFieldEnum::Both) {
            $occurrence->due_date = $date->toDateString();
        }

        if ($anchor === RecurrenceAnchorFieldEnum::PlannedEnd) {
            $occurrence->planned_end = $date->toDateString();
        }

        if ($anchor === RecurrenceAnchorFieldEnum::Both) {
            $occurrence->planned_end = $date->copy()->subDays($recurrence->offset_days)->toDateString();
        }
    }

    public function generateDue(TaskRecurrence $recurrence, Carbon $today): int
    {
        if (! $recurrence->is_active
            || $recurrence->generation_mode !== RecurrenceGenerationModeEnum::ByDate
            || $recurrence->next_run_date === null) {
            return 0;
        }

        $created = 0;

        while ($recurrence->is_active
            && $created < self::MAX_OCCURRENCES_PER_RUN
            && $recurrence->next_run_date !== null
            && $recurrence->next_run_date->lte($today)) {
            $this->makeOccurrence($recurrence, $recurrence->next_run_date->copy());
            $recurrence->generated_count++;
            $recurrence->last_generated_date = $recurrence->next_run_date->copy();
            $created++;

            if ($recurrence->stop_type === RecurrenceStopTypeEnum::Count
                && $recurrence->generated_count >= $recurrence->max_count) {
                $recurrence->is_active = false;
                break;
            }

            $next = $this->nextDate($recurrence->next_run_date->copy(), $recurrence->frequency, $recurrence->interval);

            if ($recurrence->stop_type === RecurrenceStopTypeEnum::UntilDate
                && $recurrence->until_date !== null
                && $next->gt($recurrence->until_date)) {
                $recurrence->is_active = false;
                $recurrence->next_run_date = null;
                break;
            }

            $recurrence->next_run_date = $next;
        }

        $recurrence->save();

        return $created;
    }

    public function generateOnCompletion(Task $task, Carbon $completedOn): ?Task
    {
        $recurrence = $task->recurrence;

        if ($recurrence === null
            || ! $recurrence->is_active
            || $recurrence->generation_mode !== RecurrenceGenerationModeEnum::OnCompletion) {
            return null;
        }

        $next = $this->nextDate($completedOn->copy(), $recurrence->frequency, $recurrence->interval);

        if ($recurrence->stop_type === RecurrenceStopTypeEnum::UntilDate
            && $recurrence->until_date !== null
            && $next->gt($recurrence->until_date)) {
            $recurrence->is_active = false;
            $recurrence->save();

            return null;
        }

        $occurrence = $this->makeOccurrence($recurrence, $next);
        $recurrence->generated_count++;
        $recurrence->last_generated_date = $next;

        if ($recurrence->stop_type === RecurrenceStopTypeEnum::Count
            && $recurrence->generated_count >= $recurrence->max_count) {
            $recurrence->is_active = false;
        }

        $recurrence->save();

        return $occurrence;
    }

    public function enableForTask(Task $task, array $data): TaskRecurrence
    {
        $template = $task->replicate();
        $template->is_recurrence_template = true;
        $template->recurrence_id = null;
        $template->company_id = $task->company_id;
        $template->save();

        $frequency = $data['frequency'];
        $interval = $data['interval'] ?? 1;
        $anchor = $data['anchor_field'];

        $recurrence = new TaskRecurrence;
        $recurrence->company_id = $task->company_id;
        $recurrence->task_id = $template->id;
        $recurrence->frequency = $frequency;
        $recurrence->interval = $interval;
        $recurrence->anchor_field = $anchor;
        $recurrence->offset_days = $data['offset_days'] ?? 0;
        $recurrence->generation_mode = $data['generation_mode'];
        $recurrence->stop_type = $data['stop_type'];
        $recurrence->until_date = $data['until_date'] ?? null;
        $recurrence->max_count = $data['max_count'] ?? null;
        $recurrence->generated_count = 1;
        $recurrence->is_active = true;

        if ($data['generation_mode'] === RecurrenceGenerationModeEnum::ByDate) {
            $baseField = $anchor === RecurrenceAnchorFieldEnum::PlannedEnd ? 'planned_end' : 'due_date';
            $base = $task->{$baseField} ? Carbon::parse($task->{$baseField}) : Carbon::today();
            $recurrence->next_run_date = $this->nextDate($base, $frequency, $interval);
        }

        $recurrence->save();

        $task->recurrence_id = $recurrence->id;
        $task->save();

        return $recurrence;
    }

    public function stopSeries(TaskRecurrence $recurrence): void
    {
        $recurrence->is_active = false;
        $recurrence->save();
    }
}
