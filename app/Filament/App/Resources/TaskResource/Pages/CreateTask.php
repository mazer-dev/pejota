<?php

namespace App\Filament\App\Resources\TaskResource\Pages;

use App\Enums\QuotaEnum;
use App\Filament\App\Concerns\EnforcesCreateQuota;
use App\Filament\App\Resources\TaskResource;
use App\Models\Task;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\URL;

class CreateTask extends CreateRecord
{
    use EnforcesCreateQuota;

    protected static string $resource = TaskResource::class;

    protected function quotaKey(): QuotaEnum
    {
        return QuotaEnum::TasksPerMonth;
    }

    protected function currentQuotaCount(): int
    {
        return Task::createdThisMonthCount();
    }

    protected function afterFill()
    {
        if (request()->get('parent')) {
            $task = Task::find(request()->get('parent'));

            $prefill = [
                'title' => '['.__('Subtask').'] '.$task->title,
                'client' => $task->client_id,
                'project' => $task->project_id,
                'parent_task' => $task->id,
                'due_date' => $task->due_date,
                'planned_end' => $task->planned_end,
            ];

            $this->form->fillPartially($prefill, array_keys($prefill));

            $this->redirectUrl = URL::previous();
        }
    }
}
