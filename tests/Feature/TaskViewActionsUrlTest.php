<?php

namespace Tests\Feature;

use App\Filament\App\Resources\TaskResource\Pages\CreateTask;
use App\Filament\App\Resources\TaskResource\Pages\ViewTask;
use App\Filament\App\Resources\WorkSessionResource\Pages\CreateWorkSession;
use App\Models\Company;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class TaskViewActionsUrlTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);

        $status = Status::create([
            'name' => 'To Do',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->company->id,
        ]);

        $this->task = Task::create([
            'title' => 'My task',
            'company_id' => $this->company->id,
            'status_id' => $status->id,
            'user_id' => $this->user->id,
            'priority' => 'medium',
        ]);
    }

    public function test_session_action_links_to_create_work_session_for_the_task(): void
    {
        Livewire::test(ViewTask::class, ['record' => $this->task->id])
            ->assertActionHasUrl(
                TestAction::make('session')->schemaComponent(),
                CreateWorkSession::getUrl(['task' => $this->task->id]),
            );
    }

    public function test_subtask_action_links_to_create_task_with_parent(): void
    {
        Livewire::test(ViewTask::class, ['record' => $this->task->id])
            ->assertActionHasUrl(
                TestAction::make('subtask')->schemaComponent(),
                CreateTask::getUrl(['parent' => $this->task->id]),
            );
    }

    public function test_session_action_renders_as_a_link_instead_of_mounting_an_empty_modal(): void
    {
        $html = Livewire::test(ViewTask::class, ['record' => $this->task->id])->html();

        $this->assertStringContainsString(
            'href="'.CreateWorkSession::getUrl(['task' => $this->task->id]).'"',
            $html,
        );
        $this->assertStringNotContainsString("mountAction('session'", $html);
    }
}
