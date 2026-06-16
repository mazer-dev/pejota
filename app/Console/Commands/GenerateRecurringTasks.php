<?php

namespace App\Console\Commands;

use App\Enums\RecurrenceGenerationModeEnum;
use App\Models\TaskRecurrence;
use App\Services\RecurrenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateRecurringTasks extends Command
{
    protected $signature = 'pj:generate-recurring-tasks';

    protected $description = 'Generate due occurrences for active by-date task recurrences across all companies';

    public function handle(RecurrenceService $service): int
    {
        $today = Carbon::today();
        $created = 0;

        $recurrences = TaskRecurrence::query()
            ->where('is_active', true)
            ->where('generation_mode', RecurrenceGenerationModeEnum::ByDate->value)
            ->whereNotNull('next_run_date')
            ->whereDate('next_run_date', '<=', $today)
            ->get();

        foreach ($recurrences as $recurrence) {
            $created += $service->generateDue($recurrence, $today);
        }

        $this->info("Generated {$created} recurring task occurrence(s) from {$recurrences->count()} rule(s).");

        return self::SUCCESS;
    }
}
