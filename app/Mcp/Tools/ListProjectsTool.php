<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Projetos do cliente')]
#[Description('Lista os projetos do cliente desta conexão, com descrição e contexto de IA de cada um.')]
class ListProjectsTool extends ClientScopedTool
{
    protected string $name = 'list_projects';

    public function handle(Request $request): Response
    {
        $query = $this->context()->projects()->orderByDesc('active')->orderBy('name');

        if ($request->get('only_active') === true) {
            $query->where('active', true);
        }

        $projects = $query
            ->limit($this->boundedLimit($request->get('limit'), 50, 200))
            ->get();

        return $this->json([
            'total' => $projects->count(),
            'projects' => $projects->map(fn ($project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'active' => (bool) $project->active,
                'description' => $this->excerpt($project->description, 1000),
                'ai_context' => $project->ai_context,
                'created_at' => $this->dateTime($project->created_at),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'only_active' => $schema->boolean()
                ->description('Quando verdadeiro, retorna apenas projetos ativos.'),
            'limit' => $schema->integer()
                ->description('Quantidade máxima de projetos (padrão 50, máximo 200).'),
        ];
    }
}
