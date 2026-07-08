<?php

namespace Tests\Feature\Ai\Context;

use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Ai\Context\TaskContextSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskContextSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_describes_the_task_parent_children_sessions_and_comments(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $status = Status::create([
            'name' => 'Em andamento', 'phase' => 'in_progress', 'color' => '#000', 'sort_order' => 1, 'active' => true, 'company_id' => $companyId,
        ]);

        $parent = Task::create([
            'title' => 'Projeto financeiro', 'status_id' => $status->id, 'company_id' => $companyId,
        ]);

        $task = Task::create([
            'title' => 'Configurar cobranças', 'status_id' => $status->id, 'company_id' => $companyId,
            'parent_id' => $parent->id, 'priority' => 'high', 'due_date' => now()->addDays(3)->toDateString(),
            'description' => '<p>Integrar gateway de pagamento.</p>',
        ]);

        Task::create([
            'title' => 'Subtarefa 1', 'status_id' => $status->id, 'company_id' => $companyId, 'parent_id' => $task->id,
        ]);

        WorkSession::create([
            'title' => 'Sessão 1', 'task_id' => $task->id, 'company_id' => $companyId, 'user_id' => $user->id,
            'start' => now()->subHour(), 'end' => now(),
        ]);

        $task->filamentComments()->create([
            'user_id' => $user->id,
            'subject_type' => $task->getMorphClass(),
            'comment' => 'Cliente confirmou o gateway.',
        ]);

        $context = (new TaskContextSection)->build($task->fresh());

        $this->assertStringContainsString('Título: Configurar cobranças', $context);
        $this->assertStringContainsString('Prioridade: high', $context);
        $this->assertStringContainsString('Integrar gateway de pagamento.', $context);
        $this->assertStringContainsString('Tarefa pai: Projeto financeiro', $context);
        $this->assertStringContainsString('Subtarefas (1):', $context);
        $this->assertStringContainsString('Subtarefa 1', $context);
        $this->assertStringContainsString('Sessões de trabalho: 1 sessão(ões)', $context);
        $this->assertStringContainsString('Cliente confirmou o gateway.', $context);
    }
}
