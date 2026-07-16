<?php

namespace App\Livewire\Projects;

use App\Filament\App\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ListTasks extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
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
