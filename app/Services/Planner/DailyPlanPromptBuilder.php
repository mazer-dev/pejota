<?php

namespace App\Services\Planner;

use App\Enums\DailyPlanModeEnum;
use App\Helpers\PejotaHelper;
use App\Services\Ai\Context\PromptGuard;
use Carbon\CarbonImmutable;

class DailyPlanPromptBuilder
{
    public function build(DailyPlanContext $context, DailyPlanModeEnum $mode, CarbonImmutable $date): string
    {
        $timezone = PejotaHelper::getUserTimeZoneOrDefault();
        $today = $date->setTimezone($timezone)->locale('pt_BR');
        $capacity = PejotaHelper::formatDuration($context->capacityMinutes);
        $followupSilenceDays = max(1, (int) config('services.planner.followup_silence_days', 2));

        $role = [
            'Você é o planejador diário do Luiz, freelancer solo que gerencia clientes, projetos, tarefas, faturas, contratos e conversas de WhatsApp no PeJota.',
            'Hoje é '.$today->isoFormat('dddd, DD/MM/YYYY')." (fuso {$timezone}).",
            'Monte o plano de trabalho de hoje a partir do panorama completo abaixo: o que fazer, em que ordem e com quanto tempo para cada item.',
        ];

        $output = [
            'Responda com UM ÚNICO objeto JSON, sem nenhum texto fora dele e sem cercas de código, neste formato:',
            '{"summary": "2-3 frases sobre o foco do dia", "items": [{"type": "task|follow_up|invoice|contract|habit|admin", "title": "string curta", "estimated_minutes": 45, "reason": "por que este item, citando o dado concreto", "task_id": null, "invoice_id": null, "contract_id": null, "client_id": null, "whatsapp_conversation_id": null, "suggested_message": null}], "warnings": ["avisos, se houver"]}',
            'Os campos *_id referenciam os ids entre colchetes no contexto ([tarefa #12], [fatura #3], [contrato #2], conversa #5). Use APENAS ids presentes no contexto; nunca invente ids, tarefas, valores ou prazos.',
            '"suggested_message" só em itens follow_up: o texto pronto da mensagem, em português informal-profissional, educado e sem pressão.',
        ];

        $rules = [
            "1. A soma de estimated_minutes NÃO pode passar do tempo disponível para este plano ({$capacity}); deixe ~15% de folga para imprevistos. O que esse tempo representa (o que resta do dia ou um tempo extra que o Luiz pediu) está descrito no panorama.",
            '2. Tarefa marcada [BLOQUEADA] ou [POSSIVELMENTE BLOQUEADA] NÃO entra como item type=task: mesmo havendo tarefa aberta, não há trabalho a fazer nela agora. Se o silêncio do cliente for de '.$followupSilenceDays.' dia(s) ou mais, gere no máximo UM item follow_up por cliente com suggested_message.',
            '3. Tarefa marcada [CLIENTE AGUARDANDO SUA RESPOSTA] é prioridade máxima: responder cliente vem antes de tudo.',
            '4. Ordem de prioridade: (1) clientes esperando resposta; (2) faturas vencidas/cobranças; (3) tarefas atrasadas acionáveis; (4) tarefas com vencimento próximo; (5) faturas a emitir e contratos terminando; (6) demais tarefas por prioridade; (7) hábitos.',
            '5. Use a estimativa da tarefa quando existir; sem estimativa, use bom senso (mínimo 15 min). Itens administrativos (follow_up, cobrança): 10 a 15 min.',
            '6. Hábitos ainda pendentes hoje sempre entram (itens type=habit, curtos), especialmente os com sequência ativa.',
            '7. Cada reason cita o dado concreto que justifica o item (ex.: "fatura vence amanhã", "cliente sem resposta há 2 dias"). Não invente nada fora do contexto.',
            '7b. Em itens de responder cliente ou follow_up, o reason DEVE citar entre aspas um trecho curto da última mensagem relevante da conversa (ela aparece nos marcadores das tarefas e no resumo das conversas), para o Luiz saber exatamente do que se trata sem abrir a conversa. Se a última mensagem for um anexo sem texto (ex.: [image sem texto]), diga isso: "a cliente enviou uma imagem que aguarda retorno".',
            '8. Se a capacidade não comportar tudo que é urgente, priorize e explique o que ficou de fora em warnings.',
        ];

        if ($mode === DailyPlanModeEnum::LIGHT) {
            $rules[] = '9. HOJE É DIA DE FOLGA: inclua APENAS urgências reais (fatura vencendo/vencida hoje, cliente esperando algo crítico). Sem urgências, retorne items vazio e um summary curto dizendo que não há nada urgente e desejando bom descanso.';
        }

        return implode("\n\n", [
            implode("\n", $role),
            implode("\n", $output),
            "Regras de planejamento:\n".implode("\n", $rules),
            PromptGuard::instruction(),
            "Panorama completo de hoje:\n".PromptGuard::wrap($context->text),
            'Gere o plano agora. Apenas o JSON.',
        ]);
    }
}
