<?php

namespace App\Mcp\Tools;

use App\Enums\StatusPhaseEnum;
use App\Models\Status;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Visão geral do cliente')]
#[Description('Retorna o cadastro do cliente desta conexão, o contexto de IA escrito sobre ele, a última análise gerada e um resumo do que existe (projetos, tarefas por fase, notas e conversas). Comece por aqui.')]
class ClientOverviewTool extends ClientScopedTool
{
    protected string $name = 'client_overview';

    public function handle(Request $request): Response
    {
        $context = $this->context();
        $client = $this->client();

        $statusesByPhase = Status::query()
            ->where('company_id', $context->companyId())
            ->pluck('phase', 'id');

        $tasksByStatus = $context->tasks()
            ->selectRaw('status_id, count(*) as total')
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        $tasksByPhase = [];
        foreach (StatusPhaseEnum::cases() as $phase) {
            $tasksByPhase[$phase->value] = 0;
        }

        foreach ($tasksByStatus as $statusId => $total) {
            $phase = $statusesByPhase[$statusId] ?? null;

            if ($phase !== null && array_key_exists($phase, $tasksByPhase)) {
                $tasksByPhase[$phase] += (int) $total;
            }
        }

        $latestAnalysis = $context->analyses()->latest('created_at')->first();

        return $this->json([
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'tradename' => $client->tradename,
                'email' => $client->email,
                'phone' => $client->phone,
                'created_at' => $this->dateTime($client->created_at),
            ],
            'ai_context' => $client->ai_context,
            'latest_analysis' => $latestAnalysis === null ? null : [
                'created_at' => $this->dateTime($latestAnalysis->created_at),
                'content' => $latestAnalysis->content,
            ],
            'summary' => [
                'projects' => $context->projects()->count(),
                'tasks_total' => array_sum($tasksByPhase),
                'tasks_by_phase' => $tasksByPhase,
                'notes' => $context->notes()->count(),
                'whatsapp_conversations' => $context->conversations()->count(),
                'whatsapp_messages' => $context->messages()->count(),
            ],
            'access' => [
                'mode' => 'read-only',
                'scope' => 'Somente os dados deste cliente.',
            ],
        ]);
    }
}
