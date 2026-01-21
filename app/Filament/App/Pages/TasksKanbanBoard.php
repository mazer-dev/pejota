<?php

namespace App\Filament\App\Pages;

use App\Models\Task;
use App\Models\Status;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class TasksKanbanBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    
    protected static string $view = 'filament.app.pages.tasks-kanban-board';
    
    protected static ?string $navigationGroup = 'Daily Work';
    
    protected static ?string $navigationLabel = 'Tasks Board';
    
    protected static ?int $navigationSort = 2;

    public function getStatuses(): Collection
    {
        return Status::orderBy('sort_order')->get();
    }

    public function getTasks(): Collection
    {
        return Task::with(['project', 'assignee', 'status'])
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function updateTaskStatus(int $taskId, int $statusId): void
    {
        Task::find($taskId)?->update(['status_id' => $statusId]);
    }
}