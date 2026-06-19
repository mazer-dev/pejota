<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkSession;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class WorkSessionsTopNav extends Component
{
    public ?string $newTitle = null;

    public ?int $newClient = null;

    public ?int $newProject = null;

    public ?int $newTask = null;

    public string $clientSearch = '';

    public string $projectSearch = '';

    public string $taskSearch = '';

    public function startSession(): void
    {
        $session = new WorkSession([
            'title' => $this->newTitle ?: __('Work session'),
            'company_id' => auth()->user()->company->id,
            'start' => now(),
            'is_running' => true,
            'client_id' => $this->newClient,
            'project_id' => $this->newProject,
            'task_id' => $this->newTask,
        ]);

        $session->rate = $session->resolveRate();
        $session->currency = $session->resolveCurrency();
        $session->billable = $session->resolveBillable();
        $session->save();

        $this->reset([
            'newTitle', 'newClient', 'newProject', 'newTask',
            'clientSearch', 'projectSearch', 'taskSearch',
        ]);
    }

    public function stopSession(int $id): void
    {
        $session = WorkSession::where('is_running', true)->find($id);

        $session?->finish();
    }

    public function updatedNewClient(): void
    {
        $this->reset(['newProject', 'newTask', 'projectSearch', 'taskSearch']);
    }

    public function updatedNewProject(): void
    {
        $this->reset(['newTask', 'taskSearch']);
    }

    /**
     * @return Collection<int, WorkSession>
     */
    public function getRunningSessionsProperty(): Collection
    {
        return WorkSession::with('client')
            ->where('is_running', true)
            ->orderBy('start')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function getClientOptionsProperty(): array
    {
        return Client::query()
            ->when($this->clientSearch, fn ($q) => $q->where('name', 'like', "%{$this->clientSearch}%"))
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    public function getProjectOptionsProperty(): array
    {
        return Project::query()
            ->when($this->newClient, fn ($q) => $q->where('client_id', $this->newClient))
            ->when($this->projectSearch, fn ($q) => $q->where('name', 'like', "%{$this->projectSearch}%"))
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    public function getTaskOptionsProperty(): array
    {
        return Task::query()
            ->when($this->newProject, fn ($q) => $q->where('project_id', $this->newProject))
            ->when(! $this->newProject && $this->newClient, fn ($q) => $q->where('client_id', $this->newClient))
            ->when($this->taskSearch, fn ($q) => $q->where('title', 'like', "%{$this->taskSearch}%"))
            ->orderBy('title')
            ->limit(50)
            ->pluck('title', 'id')
            ->toArray();
    }

    public function getSelectedClientLabelProperty(): ?string
    {
        return $this->newClient ? Client::find($this->newClient)?->name : null;
    }

    public function getSelectedProjectLabelProperty(): ?string
    {
        return $this->newProject ? Project::find($this->newProject)?->name : null;
    }

    public function getSelectedTaskLabelProperty(): ?string
    {
        return $this->newTask ? Task::find($this->newTask)?->title : null;
    }

    public function render(): View
    {
        $running = $this->runningSessions;

        return view('livewire.work-sessions-top-nav', [
            'running' => $running,
            'count' => $running->count(),
            'clientOptions' => $this->clientOptions,
            'projectOptions' => $this->projectOptions,
            'taskOptions' => $this->taskOptions,
            'selectedClientLabel' => $this->selectedClientLabel,
            'selectedProjectLabel' => $this->selectedProjectLabel,
            'selectedTaskLabel' => $this->selectedTaskLabel,
        ]);
    }
}
