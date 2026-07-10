<?php

namespace Tests\Feature;

use App\Livewire\WorkSessionsTopNav;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class WorkSessionsTopNavTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
    }

    public function test_lists_running_sessions(): void
    {
        WorkSession::create([
            'title' => 'Running A',
            'company_id' => $this->company->id,
            'start' => now(),
            'is_running' => true,
            'rate' => 0,
        ]);

        Livewire::test(WorkSessionsTopNav::class)
            ->assertSee('Running A')
            ->assertSee('1');
    }

    public function test_start_session_creates_running_session_with_resolved_rate(): void
    {
        $client = Client::create([
            'name' => 'Acme',
            'company_id' => $this->company->id,
            'currency' => 'BRL',
            'default_hourly_rate' => 60.00,
            'billable_default' => true,
        ]);

        Livewire::test(WorkSessionsTopNav::class)
            ->set('newTitle', 'Quick start')
            ->set('newClient', $client->id)
            ->call('startSession');

        $session = WorkSession::where('title', 'Quick start')->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->is_running);
        $this->assertEquals(60.00, $session->rate);
        $this->assertSame('BRL', $session->currency);
        $this->assertTrue($session->billable);
    }

    public function test_stop_session_finishes_it(): void
    {
        $session = WorkSession::create([
            'title' => 'Stop me',
            'company_id' => $this->company->id,
            'start' => now()->subHour(),
            'is_running' => true,
            'rate' => 0,
        ]);

        Livewire::test(WorkSessionsTopNav::class)
            ->call('stopSession', $session->id);

        $session->refresh();
        $this->assertFalse($session->is_running);
        $this->assertNotNull($session->end);
        $this->assertGreaterThanOrEqual(59, $session->duration);
    }

    public function test_client_search_filters_options(): void
    {
        Client::create(['name' => 'Acme', 'company_id' => $this->company->id]);
        Client::create(['name' => 'Globex', 'company_id' => $this->company->id]);

        Livewire::test(WorkSessionsTopNav::class)
            ->set('clientSearch', 'Acme')
            ->assertSee('Acme')
            ->assertDontSee('Globex');
    }

    public function test_changing_client_resets_project_and_task(): void
    {
        Livewire::test(WorkSessionsTopNav::class)
            ->set('newClient', 1)
            ->set('newProject', 5)
            ->set('newTask', 9)
            ->set('newClient', 2)
            ->assertSet('newProject', null)
            ->assertSet('newTask', null);
    }

    public function test_changing_project_resets_task(): void
    {
        Livewire::test(WorkSessionsTopNav::class)
            ->set('newProject', 5)
            ->set('newTask', 9)
            ->set('newProject', 6)
            ->assertSet('newTask', null);
    }

    public function test_selecting_task_fills_its_project_and_client(): void
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id]);
        $project = Project::create([
            'name' => 'Apollo',
            'company_id' => $this->company->id,
            'client_id' => $client->id,
        ]);
        $status = Status::create([
            'name' => 'To Do',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->company->id,
        ]);
        $task = Task::create([
            'title' => 'Build feature',
            'status_id' => $status->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
        ]);

        Livewire::test(WorkSessionsTopNav::class)
            ->set('newTask', $task->id)
            ->assertSet('newProject', $project->id)
            ->assertSet('newClient', $client->id);
    }

    public function test_selecting_project_fills_its_client(): void
    {
        $client = Client::create(['name' => 'Globex', 'company_id' => $this->company->id]);
        $project = Project::create([
            'name' => 'Zeus',
            'company_id' => $this->company->id,
            'client_id' => $client->id,
        ]);

        Livewire::test(WorkSessionsTopNav::class)
            ->set('newProject', $project->id)
            ->assertSet('newClient', $client->id);
    }

    public function test_selected_client_label_is_shown(): void
    {
        $client = Client::create(['name' => 'Umbrella Corp', 'company_id' => $this->company->id]);

        Livewire::test(WorkSessionsTopNav::class)
            ->set('newClient', $client->id)
            ->assertSee('Umbrella Corp');
    }
}
