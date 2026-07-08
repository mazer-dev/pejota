<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Resources\TaskResource\Pages\ViewTask;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\AiCliRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class TaskAiActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(User $user): Task
    {
        $status = Status::create([
            'name' => 'A Fazer', 'phase' => 'todo', 'color' => '#000', 'sort_order' => 1, 'active' => true, 'company_id' => $user->company->id,
        ]);

        return Task::create([
            'title' => 'Configurar cobranças recorrentes',
            'status_id' => $status->id,
            'company_id' => $user->company->id,
        ]);
    }

    public function test_ai_summary_action_fills_the_modal_with_the_generated_summary(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $this->makeTask($user);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn('Resumo pronto para envio ao cliente.');
        $this->instance(AiCliRunner::class, $runner);

        Livewire::test(ViewTask::class, ['record' => $task->id])
            ->mountInfolistAction('taskAiActions', 'aiSummary')
            ->assertInfolistActionDataSet([
                'summary' => 'Resumo pronto para envio ao cliente.',
            ]);
    }

    public function test_ai_subtasks_action_creates_selected_children_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $this->makeTask($user);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn(
            '[{"title": "Configurar gateway", "description": "Integrar com o provedor", "kind": "tecnica"},'
            .' {"title": "Pedir acesso ao painel", "description": "", "kind": "comunicacao"}]'
        );
        $this->instance(AiCliRunner::class, $runner);

        Livewire::test(ViewTask::class, ['record' => $task->id])
            ->callInfolistAction('taskAiActions', 'aiSubtasks');

        $this->assertDatabaseHas('tasks', [
            'title' => 'Configurar gateway',
            'parent_id' => $task->id,
        ]);
        $this->assertDatabaseHas('tasks', [
            'title' => 'Pedir acesso ao painel',
            'parent_id' => $task->id,
        ]);

        $technical = Task::where('title', 'Configurar gateway')->first();
        $communication = Task::where('title', 'Pedir acesso ao painel')->first();

        $this->assertFalse($technical->tags->pluck('name')->contains(Task::TAG_COMMUNICATION));
        $this->assertTrue($communication->tags->pluck('name')->contains(Task::TAG_COMMUNICATION));
    }

    public function test_it_does_not_call_the_ai_when_generating_subtasks_is_not_confirmed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $task = $this->makeTask($user);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn('[]');
        $this->instance(AiCliRunner::class, $runner);

        Livewire::test(ViewTask::class, ['record' => $task->id])
            ->mountInfolistAction('taskAiActions', 'aiSubtasks');

        $this->assertDatabaseCount('tasks', 1);
    }
}
