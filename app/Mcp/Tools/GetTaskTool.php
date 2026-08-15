<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Models\WorkSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Detalhe de uma tarefa')]
#[Description('Retorna uma tarefa do cliente desta conexão por inteiro: descrição completa, checklist, subtarefas, notas ligadas a ela e tempo trabalhado. Tarefas de outros clientes não são acessíveis.')]
class GetTaskTool extends ClientScopedTool
{
    protected string $name = 'get_task';

    public function handle(Request $request): Response
    {
        $taskId = $request->get('task_id');

        if (! is_numeric($taskId)) {
            return Response::error('Informe o task_id da tarefa.');
        }

        $task = $this->context()->tasks()
            ->with(['status', 'project', 'parent', 'children.status', 'tags'])
            ->whereKey((int) $taskId)
            ->first();

        if (! $task instanceof Task) {
            return Response::error('Tarefa não encontrada para este cliente.');
        }

        $workSessions = WorkSession::query()
            ->where('company_id', $this->context()->companyId())
            ->where('task_id', $task->id)
            ->get();

        $notes = $this->context()->notes()
            ->where('task_id', $task->id)
            ->latest('created_at')
            ->get();

        return $this->json([
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority,
                'status' => $task->status?->name,
                'phase' => $task->status?->phase,
                'project' => $task->project?->name,
                'parent' => $task->parent?->title,
                'due_date' => $this->date($task->due_date),
                'planned_start' => $this->date($task->planned_start),
                'planned_end' => $this->date($task->planned_end),
                'actual_start' => $this->date($task->actual_start),
                'actual_end' => $this->date($task->actual_end),
                'checklist' => $task->checklist,
                'tags' => $task->tags->pluck('name')->all(),
                'created_at' => $this->dateTime($task->created_at),
                'updated_at' => $this->dateTime($task->updated_at),
            ],
            'children' => $task->children->map(fn (Task $child): array => [
                'id' => $child->id,
                'title' => $child->title,
                'phase' => $child->status?->phase,
            ])->all(),
            'work' => [
                'sessions' => $workSessions->count(),
                'total_minutes' => (int) $workSessions->sum('duration'),
            ],
            'notes' => $notes->map(fn ($note): array => [
                'id' => $note->id,
                'title' => $note->title,
                'created_at' => $this->dateTime($note->created_at),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()
                ->description('Id da tarefa, obtido em list_tasks.')
                ->required(),
        ];
    }
}
