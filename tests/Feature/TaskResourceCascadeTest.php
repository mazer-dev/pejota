<?php

namespace Tests\Feature;

use App\Filament\App\Resources\TaskResource\Pages\CreateTask;
use App\Models\Client;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class TaskResourceCascadeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Landlord::addTenant('company_id', $this->user->company->id);
    }

    private function makeStatus(): Status
    {
        return Status::create([
            'name' => 'To Do',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->user->company->id,
        ]);
    }

    public function test_selecting_project_fills_client(): void
    {
        $this->makeStatus();
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->user->company->id]);
        $project = Project::create([
            'name' => 'Apollo',
            'company_id' => $this->user->company->id,
            'client_id' => $client->id,
            'active' => true,
        ]);

        Livewire::test(CreateTask::class)
            ->set('data.project', $project->id)
            ->assertFormSet(['client' => $client->id]);
    }

    public function test_creating_subtask_via_url_copies_parent_planned_end(): void
    {
        $status = $this->makeStatus();
        $parent = Task::create([
            'title' => 'Parent task',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'due_date' => '2026-07-20',
            'planned_end' => '2026-07-15',
        ]);

        Livewire::withQueryParams(['parent' => $parent->id])
            ->test(CreateTask::class)
            ->assertSet('data.due_date', $parent->due_date)
            ->assertSet('data.planned_end', $parent->planned_end);
    }

    public function test_selecting_parent_task_fills_project_and_client(): void
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->user->company->id]);
        $project = Project::create([
            'name' => 'Apollo',
            'company_id' => $this->user->company->id,
            'client_id' => $client->id,
            'active' => true,
        ]);
        $status = $this->makeStatus();
        $parent = Task::create([
            'title' => 'Parent task',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
        ]);

        Livewire::test(CreateTask::class)
            ->set('data.parent_task', $parent->id)
            ->assertFormSet([
                'project' => $project->id,
                'client' => $client->id,
            ]);
    }
}
