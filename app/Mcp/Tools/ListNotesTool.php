<?php

namespace App\Mcp\Tools;

use App\Models\Note;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Notas do cliente')]
#[Description('Lista as notas do cliente desta conexão com o conteúdo já convertido para texto.')]
class ListNotesTool extends ClientScopedTool
{
    protected string $name = 'list_notes';

    public function handle(Request $request): Response
    {
        $query = $this->context()->notes()
            ->with('project')
            ->latest('created_at');

        $search = $request->get('search');
        if (is_string($search) && trim($search) !== '') {
            $query->where('title', 'like', '%'.trim($search).'%');
        }

        $notes = $query
            ->limit($this->boundedLimit($request->get('limit'), 20, 100))
            ->get();

        return $this->json([
            'total' => $notes->count(),
            'notes' => $notes->map(fn (Note $note): array => [
                'id' => $note->id,
                'title' => $note->title,
                'project' => $note->project?->name,
                'content' => Str::limit($this->flattenContent($note->content), 2000),
                'created_at' => $this->dateTime($note->created_at),
            ])->all(),
        ]);
    }

    /**
     * Notes are stored as Filament builder blocks; the agent only cares about
     * the text inside them.
     */
    protected function flattenContent(mixed $content): string
    {
        if (is_string($content)) {
            return trim(strip_tags($content));
        }

        if (! is_array($content)) {
            return '';
        }

        $pieces = [];

        array_walk_recursive($content, function ($value, $key) use (&$pieces): void {
            if (is_string($value) && $key === 'content' && trim($value) !== '') {
                $pieces[] = trim(strip_tags($value));
            }
        });

        return implode("\n\n", $pieces);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Texto procurado no título da nota.'),
            'limit' => $schema->integer()
                ->description('Quantidade máxima de notas (padrão 20, máximo 100).'),
        ];
    }
}
