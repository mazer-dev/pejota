<?php

namespace Tests\Feature\Ai\Context;

use App\Models\Client;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\Context\TasksContextSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksContextSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_without_client_or_project(): void
    {
        $this->assertNull((new TasksContextSection)->build());
    }

    public function test_it_groups_open_tasks_by_phase_and_highlights_overdue_and_upcoming(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        $todo = Status::create([
            'name' => 'A Fazer', 'phase' => 'todo', 'color' => '#000', 'sort_order' => 1, 'active' => true, 'company_id' => $companyId,
        ]);
        $closedStatus = Status::create([
            'name' => 'Concluído', 'phase' => 'closed', 'color' => '#000', 'sort_order' => 2, 'active' => true, 'company_id' => $companyId,
        ]);

        Task::create([
            'title' => 'Tarefa atrasada', 'status_id' => $todo->id, 'company_id' => $companyId,
            'client_id' => $client->id, 'due_date' => now()->subDays(3)->toDateString(),
        ]);

        Task::create([
            'title' => 'Tarefa futura', 'status_id' => $todo->id, 'company_id' => $companyId,
            'client_id' => $client->id, 'due_date' => now()->addDays(5)->toDateString(),
        ]);

        Task::create([
            'title' => 'Tarefa concluída', 'status_id' => $closedStatus->id, 'company_id' => $companyId,
            'client_id' => $client->id, 'actual_end' => now()->subDay()->toDateString(),
        ]);

        $context = (new TasksContextSection)->build($client);

        $this->assertNotNull($context);
        $this->assertStringContainsString('Tarefas:', $context);
        $this->assertStringContainsString('A Fazer (2):', $context);
        $this->assertStringContainsString('Tarefa atrasada - ATRASADA há 3 dia(s)', $context);
        $this->assertStringContainsString('Tarefa futura - vence em', $context);
        $this->assertStringContainsString('Próximos vencimentos:', $context);
        $this->assertStringContainsString('Últimas concluídas:', $context);
        $this->assertStringContainsString('Tarefa concluída - concluída em', $context);
    }

    public function test_it_returns_null_when_client_has_no_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = Client::create(['company_id' => $user->company->id, 'name' => 'Sem tarefas']);

        $this->assertNull((new TasksContextSection)->build($client));
    }
}
