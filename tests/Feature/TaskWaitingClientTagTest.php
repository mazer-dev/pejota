<?php

namespace Tests\Feature;

use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskWaitingClientTagTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Status $todo;

    private Status $closed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $companyId = $this->user->company->id;

        $this->todo = Status::create([
            'name' => 'A Fazer', 'phase' => 'todo', 'color' => '#000', 'sort_order' => 1, 'active' => true, 'company_id' => $companyId,
        ]);
        $this->closed = Status::create([
            'name' => 'Concluído', 'phase' => 'closed', 'color' => '#000', 'sort_order' => 2, 'active' => true, 'company_id' => $companyId,
        ]);
    }

    private function makeWaitingTask(): Task
    {
        $task = Task::create([
            'title' => 'Obter acesso',
            'status_id' => $this->todo->id,
            'company_id' => $this->user->company->id,
        ]);

        $task->attachTag(Task::TAG_WAITING_CLIENT);

        return $task;
    }

    public function test_closing_the_task_removes_the_waiting_client_tag(): void
    {
        $task = $this->makeWaitingTask();

        $task->update(['status_id' => $this->closed->id]);

        $this->assertFalse($task->fresh()->tags->pluck('name')->contains(Task::TAG_WAITING_CLIENT));
    }

    public function test_moving_to_a_non_closed_status_keeps_the_tag(): void
    {
        $inProgress = Status::create([
            'name' => 'Em andamento', 'phase' => 'in_progress', 'color' => '#000', 'sort_order' => 3, 'active' => true,
            'company_id' => $this->user->company->id,
        ]);

        $task = $this->makeWaitingTask();

        $task->update(['status_id' => $inProgress->id]);

        $this->assertTrue($task->fresh()->tags->pluck('name')->contains(Task::TAG_WAITING_CLIENT));
    }

    public function test_closing_keeps_other_tags_untouched(): void
    {
        $task = $this->makeWaitingTask();
        $task->attachTag(Task::TAG_COMMUNICATION);

        $task->update(['status_id' => $this->closed->id]);

        $names = $task->fresh()->tags->pluck('name');

        $this->assertFalse($names->contains(Task::TAG_WAITING_CLIENT));
        $this->assertTrue($names->contains(Task::TAG_COMMUNICATION));
    }
}
