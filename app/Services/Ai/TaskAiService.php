<?php

namespace App\Services\Ai;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Services\Ai\Context\ClientContextBuilder;
use App\Services\Ai\Context\PromptGuard;
use App\Services\Ai\Context\TaskContextSection;
use Illuminate\Support\Str;

class TaskAiService
{
    public function __construct(
        private readonly TaskContextSection $taskContextSection,
        private readonly ClientContextBuilder $clientContextBuilder,
        private readonly AiCliRunner $cliRunner,
    ) {}

    /**
     * Generates a status report about the task, ready to be copied and sent
     * to the client. Never sends anything automatically.
     */
    public function summaryForClient(Task $task): string
    {
        $context = $this->buildTaskContext($task);

        $instructions = implode("\n", [
            'Você escreve um resumo de status de uma tarefa para ser enviado ao cliente.',
            'Responda em português do Brasil, tom profissional, objetivo e claro.',
            'Use apenas informações do contexto. Não invente prazo, preço ou decisão.',
            PromptGuard::instruction(),
            'Retorne somente o texto do resumo, pronto para copiar e enviar, sem títulos nem explicações extras.',
        ]);

        $prompt = implode("\n\n", [
            $instructions,
            "Contexto disponível:\n".PromptGuard::wrap($context),
            'Escreva o resumo agora.',
        ]);

        return trim($this->cliRunner->complete($prompt));
    }

    /**
     * Drafts the WhatsApp message that unblocks a communication task
     * (access request, missing information, approval, etc.), written in
     * Luiz's tone for that specific client — the client context includes
     * the writing-style sample. Returns only the message text; sending is
     * always a human decision.
     */
    public function draftClientMessage(Task $task): string
    {
        $context = $this->buildTaskContext($task);

        $instructions = implode("\n", [
            'Você escreve a mensagem de WhatsApp que o Luiz deve enviar ao cliente para destravar a tarefa descrita no contexto (pedido de acesso, informação, aprovação etc.).',
            'Responda em português do Brasil, com tom profissional, natural, direto e humano.',
            'Imite o tom e o estilo das mensagens de exemplo do Luiz, quando disponíveis no contexto.',
            'Use apenas informações do contexto. Não invente preço, prazo, escopo, promessa ou decisão.',
            PromptGuard::instruction(),
            'Retorne somente o texto da mensagem, sem aspas, títulos ou explicações.',
        ]);

        $prompt = implode("\n\n", [
            $instructions,
            "Contexto disponível:\n".PromptGuard::wrap($context),
            'Escreva a mensagem agora.',
        ]);

        return trim($this->cliRunner->complete($prompt));
    }

    /**
     * Asks the AI to break the task down into subtasks. Returns a plain
     * array of ['title' => string, 'description' => ?string, 'kind' =>
     * 'tecnica'|'comunicacao'], tolerant of markdown code fences around the
     * JSON and of a missing "kind" (defaults to 'tecnica' for backwards
     * compatibility). Never writes to the database: the caller is
     * responsible for creating tasks after user confirmation.
     *
     * @return array<int, array{title: string, description: ?string, kind: string}>
     */
    public function suggestSubtasks(Task $task): array
    {
        $context = $this->buildTaskContext($task);

        $instructions = implode("\n", [
            'Você quebra uma tarefa em subtarefas menores e acionáveis.',
            'Responda em português do Brasil.',
            'Retorne SOMENTE um array JSON, sem cercas de código, sem texto antes ou depois, no formato:',
            '[{"title": "...", "description": "...", "kind": "tecnica"}]',
            'O campo "kind" deve ser "comunicacao" quando a subtarefa consistir em falar com o cliente (pedir acesso, informação, aprovação, retorno) e "tecnica" quando for trabalho de execução.',
            'O campo "description" pode ser uma string vazia se não houver detalhe adicional.',
            'Sugira entre 2 e 8 subtarefas. Não invente prazos ou valores.',
            PromptGuard::instruction(),
        ]);

        $prompt = implode("\n\n", [
            $instructions,
            "Contexto disponível:\n".PromptGuard::wrap($context),
            'Gere a lista de subtarefas agora.',
        ]);

        $response = trim($this->cliRunner->complete($prompt));

        return $this->parseSubtasks($response);
    }

    /**
     * Suggests a description for a task from its title and, optionally, the
     * selected client/project. The caller fills the form field after user
     * confirmation; nothing is persisted here.
     */
    public function suggestDescription(string $title, ?Client $client = null, ?Project $project = null): string
    {
        $context = collect([
            "Título da tarefa: {$title}",
            $this->clientContextBuilder->forClientFacts($client, $project) ?: null,
        ])->filter()->implode("\n\n");

        $instructions = implode("\n", [
            'Você sugere a descrição de uma tarefa a partir do seu título e, quando disponível, do cliente/projeto selecionado.',
            'Responda em português do Brasil, de forma objetiva, em um ou dois parágrafos curtos.',
            'Não invente prazo, preço ou escopo que não esteja implícito no título/contexto.',
            PromptGuard::instruction(),
            'Retorne somente o texto da descrição.',
        ]);

        $prompt = implode("\n\n", [
            $instructions,
            "Contexto disponível:\n".PromptGuard::wrap($context),
            'Escreva a descrição agora.',
        ]);

        return trim($this->cliRunner->complete($prompt));
    }

    private function buildTaskContext(Task $task): string
    {
        $task->loadMissing(['client', 'project']);

        $sections = [
            $this->taskContextSection->build($task),
            $this->clientContextBuilder->forClientFacts($task->client, $task->project) ?: null,
        ];

        return collect($sections)->filter()->implode("\n\n");
    }

    /**
     * @return array<int, array{title: string, description: ?string, kind: string}>
     */
    private function parseSubtasks(string $response): array
    {
        $json = $this->stripCodeFences($response);

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($item): bool => is_array($item) && filled($item['title'] ?? null))
            ->map(fn (array $item): array => [
                'title' => (string) $item['title'],
                'description' => filled($item['description'] ?? null) ? (string) $item['description'] : null,
                'kind' => ($item['kind'] ?? null) === Task::AI_KIND_COMMUNICATION ? Task::AI_KIND_COMMUNICATION : Task::AI_KIND_TECHNICAL,
            ])
            ->values()
            ->all();
    }

    private function stripCodeFences(string $response): string
    {
        $trimmed = trim($response);

        if (Str::startsWith($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/```\s*$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }
}
