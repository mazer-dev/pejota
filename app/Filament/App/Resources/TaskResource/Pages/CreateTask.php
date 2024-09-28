<?php

namespace App\Filament\App\Resources\TaskResource\Pages;

use App\Filament\App\Resources\TaskResource;
use App\Models\Task;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\URL;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function afterFill()
    {
        if (request()->get('parent')) {
            $task = Task::find(request()->get('parent'));

            $this->data['title'] = '[' . __('Subtask') . '] ' . $task->title;
            $this->data['client'] = $task->client_id;
            $this->data['project'] = $task->project_id;
            $this->data['parent_task'] = $task->id;
            $this->data['due_date'] = $task->due_date;
            $this->data['planned_end'] = $task->pkanned_end;

            $this->redirectUrl = URL::previous();
        }
    }

}
