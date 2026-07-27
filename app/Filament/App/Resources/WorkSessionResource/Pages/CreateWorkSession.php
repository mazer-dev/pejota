<?php

namespace App\Filament\App\Resources\WorkSessionResource\Pages;

use App\Filament\App\Resources\WorkSessionResource;
use App\Models\Task;
use App\Models\WorkSession;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkSession extends CreateRecord
{
    protected static string $resource = WorkSessionResource::class;

    /**
     * Merges the task prefill over the state the form defaults just produced.
     * `fillPartially()` is what makes both halves work: unlike `fill($state)`,
     * which replaces the whole state and drops every default, it writes only
     * the given paths, and it runs each field's state casts — without which a
     * Carbon would reach `$data` raw and the datetime input would reject it.
     */
    protected function afterFill(): void
    {
        if (! request()->get('task')) {
            return;
        }

        $task = Task::find(request()->get('task'));

        if ($task) {
            $prefill = self::getFillFormArray($task);

            $this->form->fillPartially($prefill, array_keys($prefill));
        }
    }

    public static function getFillFormArray(Task $task): array
    {
        $fill = [
            'title' => $task->title,
            'start' => now(),
            'is_running' => true,
            ...WorkSession::resolveBillingFor(
                clientId: $task->client_id,
                projectId: $task->project_id,
                taskId: $task->id,
            ),
        ];

        if ($task->client_id) {
            $fill['client'] = $task->client_id;
        }

        if ($task->project_id) {
            $fill['project'] = $task->project_id;
        }

        if ($task->id) {
            $fill['task'] = $task->id;
        }

        return $fill;
    }
}
