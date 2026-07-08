<?php

namespace App\Services\Ai;

use App\Models\Client;
use App\Models\ClientAiAnalysis;
use App\Services\Ai\Context\ClientContextBuilder;
use App\Services\Ai\Context\PromptGuard;

class ClientAnalysisService
{
    public function __construct(
        private readonly ClientContextBuilder $contextBuilder,
        private readonly AiCliRunner $cliRunner,
    ) {}

    /**
     * Generates a new relationship analysis for the client and persists it.
     * Never overwrites a previous analysis: each call appends a new row.
     */
    public function generate(Client $client): ClientAiAnalysis
    {
        $context = $this->contextBuilder->forAnalysis($client);

        $content = trim($this->cliRunner->complete($this->prompt($context)));

        return ClientAiAnalysis::create([
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'content' => $content,
        ]);
    }

    private function prompt(string $context): string
    {
        $instructions = implode("\n", [
            'Você analisa o relacionamento do Luiz/Pejota com um cliente, a partir de fatos registrados no sistema (tarefas, faturas, contratos, notas, sessões de trabalho e histórico de conversas do WhatsApp).',
            'Responda em português do Brasil, em markdown.',
            'Use exatamente estas seções, nesta ordem, com estes títulos em nível 2 (##):',
            '## Temperatura da relação',
            '## Estilo de comunicação do cliente',
            '## Compromissos em aberto',
            '## Riscos',
            '## Próximos passos recomendados',
            'Na seção "Temperatura da relação", justifique com fatos concretos do contexto (datas, atrasos, tom das mensagens).',
            'Em "Compromissos em aberto", liste separadamente o que está pendente do lado do Luiz e o que está pendente do lado do cliente.',
            'Não invente fatos que não estão no contexto.',
            PromptGuard::instruction(),
        ]);

        return implode("\n\n", [
            $instructions,
            "Contexto disponível:\n".PromptGuard::wrap($context),
            'Gere a análise agora.',
        ]);
    }
}
