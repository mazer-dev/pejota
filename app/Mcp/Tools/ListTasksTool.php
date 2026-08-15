<?php

namespace App\Mcp\Tools;

use App\Enums\StatusPhaseEnum;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Tarefas do cliente')]
#[Description('Lista as tarefas do cliente desta conexão. Aceita filtro por fase (todo, in_progress, closed), por projeto e por texto no título ou descrição.')]
class ListTasksTool extends ClientScopedTool
{
    protected string $name = 'list_tasks';

    public function handle(Request $request): Response
    {
        $query = $this->context()->tasks()
            ->with(['status', 'project', 'tags'])
            ->orderByDesc('updated_at');

        $phase = $request->get('phase');
        if (is_string($phase) && StatusPhaseEnum::tryFrom($phase) instanceof StatusPhaseEnum) {
            $query->whereHas('status', fn ($status) => $status->where('phase', $phase));
        }

        $projectId = $request->get('project_id');
        if (is_numeric($projectId)) {
            $query->where('project_id', (int) $projectId);
        }

        $search = $request->get('search');
        if (is_string($search) && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($group) use ($term): void {
                $group->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $tasks = $query
            ->limit($this->boundedLimit($request->get('limit'), 30, 100))
            ->get();

        return $this->json([
            'total' => $tasks->count(),
            'tasks' => $tasks->map(fn (Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'priority' => $task->priority,
                'status' => $task->status?->name,
                'phase' => $task->status?->phase,
                'project' => $task->project?->name,
                'due_date' => $this->date($task->due_date),
                'planned_start' => $this->date($task->planned_start),
                'planned_end' => $this->date($task->planned_end),
                'actual_start' => $this->date($task->actual_start),
                'actual_end' => $this->date($task->actual_end),
                'tags' => $task->tags->pluck('name')->all(),
                'description' => $this->excerpt($task->description, 300),
                'updated_at' => $this->dateTime($task->updated_at),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'phase' => $schema->string()
                ->enum(['todo', 'in_progress', 'closed'])
                ->description('Fase da tarefa.'),
            'project_id' => $schema->integer()
                ->description('Filtra por um projeto deste cliente (use list_projects para descobrir o id).'),
            'search' => $schema->string()
                ->description('Texto procurado no título ou na descrição.'),
            'limit' => $schema->integer()
                ->description('Quantidade máxima de tarefas (padrão 30, máximo 100).'),
        ];
    }
}
