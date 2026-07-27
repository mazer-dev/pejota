<?php

namespace App\Filament\App\Resources\WorkSessionResource\Pages;

use App\Filament\App\Resources\WorkSessionResource;
use App\Models\Task;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\URL;

class CreateWorkSession extends CreateRecord
{
    protected static string $resource = WorkSessionResource::class;

    public $redirectUrl = null;

    /**
     * Merges the task prefill over the state the form defaults just produced.
     * Assigning to `$this->data` rather than calling `fill()` again is what
     * keeps those defaults: `fill()` with an argument replaces the whole state.
     */
    protected function afterFill(): void
    {
        if (! request()->get('task')) {
            return;
        }

        $task = Task::find(request()->get('task'));

        if ($task) {
            $this->data = [
                ...$this->data,
                ...self::getFillFormArray($task),
            ];
        }

        $this->redirectUrl = URL::previous();
    }

    protected function getRedirectUrl(): string
    {
        return $this->redirectUrl ?? parent::getRedirectUrl();
    }

    public static function getFillFormArray(Task $task): array
    {
        $fill = [
            'title' => $task->title,
            'start' => now(),
            'rate' => 0,
            'is_running' => true,
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
