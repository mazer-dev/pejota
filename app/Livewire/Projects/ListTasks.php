<?php

namespace App\Livewire\Projects;

use App\Filament\App\Resources\ProjectResource;
use App\Filament\App\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ListTasks extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public Project $project;

    public function table(Table $table): Table
    {
        return TaskResource::table($table)
            ->query(
                Task::query()
                    ->where('project_id', $this->project->id)
            );
    }

    public function render(): View
    {
        return view('livewire.projects.list-tasks');
    }
}
