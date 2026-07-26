<?php

namespace App\Services\Planner;

use App\Enums\InvoiceStatusEnum;
use App\Enums\PriorityEnum;
use App\Enums\SubscriptionStatusEnum;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\ClientAiAnalysis;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the full company panorama for the daily planner in plain PHP:
 * every SELECT is deterministic and tenant-filtered here, so the AI gets
 * one complete prompt and never runs its own queries.
 *
 * The central signal is task actionability: an open task whose client has
 * been silent since our last message is flagged as (possibly) blocked and
 * must not be scheduled as regular work, only as a follow-up candidate.
 */
class DailyPlanContextBuilder
{
    public function build(Company $company, CarbonImmutable $date, PlannerCapacity $capacity, ?int $capacityOverrideMinutes = null): DailyPlanContext
    {
        $maxChars = max(1000, (int) config('services.planner.context_max_chars', 40000));

        $degradations = [
            ['messages' => null, 'conversations' => null, 'analyses' => true],
            ['messages' => 3, 'conversations' => null, 'analyses' => true],
            ['messages' => 3, 'conversations' => 8, 'analyses' => false],
        ];

        $context = null;

        foreach ($degradations as $index => $degradation) {
            $context = $this->buildOnce($company, $date, $capacity, $degradation, truncated: $index > 0, capacityOverrideMinutes: $capacityOverrideMinutes);

            if (mb_strlen($context->text) <= $maxChars) {
                return $context;
            }
        }

        return $context;
    }

    /**
     * @param  array{messages: int|null, conversations: int|null, analyses: bool}  $degradation
     */
    private function buildOnce(
        Company $company,
        CarbonImmutable $date,
        PlannerCapacity $capacity,
        array $degradation,
        bool $truncated,
        ?int $capacityOverrideMinutes = null,
    ): DailyPlanContext {
        $timezone = PejotaHelper::getUserTimeZoneOrDefault();
        $dateFormat = PejotaHelper::getUserDateFormatOrDefault();
        $today = $date->setTimezone($timezone)->startOfDay();

        $clientSignals = $this->clientConversationSignals($company);
        $tasks = $this->candidateTasks($company);
        $habits = $this->habitTasks($company);
        $invoices = $this->relevantInvoices($company, $today);
        $contracts = $this->endingContracts($company, $today);
        $conversations = $this->recentConversations(
            $company,
            $today,
            maxConversations: $degradation['conversations'] ?? (int) config('services.planner.max_conversations', 15),
        );

        $sections = array_filter([
            $this->capacitySection($company, $today, $capacity, $capacityOverrideMinutes),
            $this->tasksSection($tasks, $clientSignals, $today, $dateFormat),
            $this->habitsSection($habits),
            $this->conversationsSection(
                $conversations,
                $today,
                maxMessages: $degradation['messages'] ?? (int) config('services.planner.max_messages_per_conversation', 6),
            ),
            $this->invoicesSection($invoices, $today, $dateFormat),
            $this->unbilledWorkSection($company),
            $this->contractsSection($contracts, $today, $dateFormat),
            $this->subscriptionsSection($company, $today, $dateFormat),
            $this->notesSection($company, $today),
            $degradation['analyses'] ? $this->analysesSection($company) : null,
            $this->freshnessSection($company, $today),
        ], fn (?string $section): bool => filled($section));

        $text = implode("\n\n", $sections);

        if ($truncated) {
            $text .= "\n\n[contexto truncado: parte das conversas e análises foi omitida por limite de espaço]";
        }

        return new DailyPlanContext(
            text: $text,
            capacityMinutes: $capacityOverrideMinutes ?? max(0, $capacity->remainingTodayMinutes($today)),
            validTaskIds: $tasks->pluck('id')->merge($habits->pluck('id'))->map(fn ($id) => (int) $id)->all(),
            validInvoiceIds: $invoices->pluck('id')->map(fn ($id) => (int) $id)->all(),
            validContractIds: $contracts->pluck('id')->map(fn ($id) => (int) $id)->all(),
            validClientIds: Client::allTenants()->where('company_id', $company->id)->pluck('id')->map(fn ($id) => (int) $id)->all(),
            validConversationIds: $conversations->pluck('id')->map(fn ($id) => (int) $id)->all(),
            truncated: $truncated,
        );
    }

    private function capacitySection(Company $company, CarbonImmutable $today, PlannerCapacity $capacity, ?int $capacityOverrideMinutes = null): string
    {
        $budgetLine = $capacityOverrideMinutes !== null && $capacityOverrideMinutes > 0
            ? 'Você pediu para trabalhar '.PejotaHelper::formatDuration($capacityOverrideMinutes).' de tempo EXTRA agora, além do horário já planejado do dia. Planeje SÓ dentro desse tempo extra.'
            : 'Tempo restante hoje para o plano: '.PejotaHelper::formatDuration(max(0, $capacity->remainingTodayMinutes($today))).' (capacidade do dia menos o já trabalhado). Planeje SÓ dentro desse tempo restante.';

        $lines = [
            'Capacidade de trabalho de hoje: '.PejotaHelper::formatDuration($capacity->dayCapacityMinutes($today)).' ('.($capacity->isWorkDay($today) ? 'dia de trabalho' : 'DIA DE FOLGA').').',
            'Já trabalhado hoje: '.PejotaHelper::formatDuration($capacity->workedOnDayMinutes($today)).'.',
            $budgetLine,
            'Já trabalhado nesta semana: '.PejotaHelper::formatDuration($capacity->workedThisWeekMinutes($today)).' de '.PejotaHelper::formatDuration($capacity->weeklyMinutes()).' planejados.',
        ];

        $running = WorkSession::allTenants()
            ->where('company_id', $company->id)
            ->where('is_running', true)
            ->with(['task', 'client'])
            ->first();

        if ($running) {
            $lines[] = 'Sessão de trabalho EM ANDAMENTO agora: '
                .($running->task?->title ?? $running->title ?? 'sem título')
                .($running->client ? ' [cliente '.$running->client->name.']' : '');
        }

        return "## Capacidade e carga\n".implode("\n", $lines);
    }

    /**
     * @return Collection<int, Task>
     */
    private function candidateTasks(Company $company): Collection
    {
        $maxTasks = max(1, (int) config('services.planner.max_tasks', 60));

        $tasks = Task::allTenants()
            ->where('company_id', $company->id)
            ->with(['client', 'project', 'status', 'tags'])
            ->opened()
            ->where('is_continuous', false)
            ->get();

        $sorted = $tasks->sortBy([
            fn (Task $a, Task $b) => ($a->due_date?->timestamp ?? PHP_INT_MAX) <=> ($b->due_date?->timestamp ?? PHP_INT_MAX),
            fn (Task $a, Task $b) => $this->priorityOrder($b) <=> $this->priorityOrder($a),
        ])->values();

        $selected = $sorted->take($maxTasks);
        $selectedIds = $selected->pluck('id')->all();

        return $selected
            ->reject(fn (Task $task): bool => $task->parent_id !== null && in_array($task->parent_id, $selectedIds, true))
            ->values();
    }

    private function priorityOrder(Task $task): int
    {
        return PriorityEnum::tryFrom((string) $task->priority)?->getOrder() ?? 1;
    }

    /**
     * Latest exchanged message per client, from the newest WhatsApp
     * conversation linked to that client (groups excluded: a silent group
     * is not a blocked client). The excerpt lets the AI cite the actual
     * message in the item's reason instead of a generic "reply the client".
     *
     * @return array<int, array{from_me: bool, sent_at: CarbonImmutable, conversation_id: int, excerpt: string}>
     */
    private function clientConversationSignals(Company $company): array
    {
        $conversations = WhatsappConversation::allTenants()
            ->where('company_id', $company->id)
            ->whereNotNull('client_id')
            ->where('is_group', false)
            ->with('latestMessage')
            ->orderByDesc('last_message_at')
            ->get();

        $signals = [];

        foreach ($conversations as $conversation) {
            $clientId = (int) $conversation->client_id;
            $latest = $conversation->latestMessage;

            if ($latest === null || isset($signals[$clientId])) {
                continue;
            }

            $text = trim((string) $latest->text);

            $signals[$clientId] = [
                'from_me' => (bool) $latest->from_me,
                'sent_at' => CarbonImmutable::parse($latest->sent_at),
                'conversation_id' => (int) $conversation->id,
                'excerpt' => $text !== ''
                    ? Str::limit($text, 120)
                    : '['.($latest->message_type ?: 'mensagem').' sem texto]',
            ];
        }

        return $signals;
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @param  array<int, array{from_me: bool, sent_at: CarbonImmutable, conversation_id: int}>  $clientSignals
     */
    private function tasksSection(Collection $tasks, array $clientSignals, CarbonImmutable $today, string $dateFormat): ?string
    {
        if ($tasks->isEmpty()) {
            return "## Tarefas abertas\nNenhuma tarefa aberta.";
        }

        $thresholdHours = max(1, (int) config('services.planner.waiting_client_threshold_hours', 24));
        $now = $today->setTimeFromTimeString(now($today->timezone)->format('H:i:s'));

        $lines = $tasks->map(function (Task $task) use ($clientSignals, $today, $dateFormat, $thresholdHours, $now): string {
            $parts = ['- [tarefa #'.$task->id.']'];
            $parts[] = $task->title;

            if ($task->client) {
                $parts[] = '(cliente: '.$task->client->name.($task->project ? ', projeto: '.$task->project->name : '').')';
            } elseif ($task->project) {
                $parts[] = '(projeto: '.$task->project->name.')';
            }

            $priority = PriorityEnum::tryFrom((string) $task->priority);
            if ($priority && $priority->getOrder() !== 1) {
                $parts[] = 'prioridade '.$task->priority;
            }

            if ($task->due_date) {
                $parts[] = $task->due_date->lt($today)
                    ? 'ATRASADA há '.(int) $task->due_date->diffInDays($today).' dia(s)'
                    : 'vence em '.$task->due_date->format($dateFormat);
            }

            if ($task->effort) {
                $minutes = $task->effort_unit === 'm' ? (int) $task->effort : (int) $task->effort * 60;
                $parts[] = 'estimativa '.PejotaHelper::formatDuration($minutes);
            } else {
                $parts[] = 'sem estimativa';
            }

            $parts = array_merge($parts, $this->taskBlockMarkers($task, $clientSignals, $thresholdHours, $now));

            return implode(' - ', $parts);
        });

        return "## Tarefas abertas (candidatas ao plano)\n".$lines->implode("\n");
    }

    /**
     * @param  array<int, array{from_me: bool, sent_at: CarbonImmutable, conversation_id: int}>  $clientSignals
     * @return array<int, string>
     */
    private function taskBlockMarkers(Task $task, array $clientSignals, int $thresholdHours, CarbonImmutable $now): array
    {
        $markers = [];

        if ($task->tags->pluck('name')->contains(Task::TAG_WAITING_CLIENT)) {
            $markers[] = '[BLOQUEADA: marcada como aguardando o cliente]';
        }

        $signal = $task->client_id ? ($clientSignals[(int) $task->client_id] ?? null) : null;

        if ($signal === null) {
            return $markers;
        }

        $hours = (int) $signal['sent_at']->diffInHours($now);

        if ($signal['from_me'] && $hours >= $thresholdHours) {
            $markers[] = "[POSSIVELMENTE BLOQUEADA: sua última mensagem para este cliente foi há {$hours}h e ele não respondeu (conversa #{$signal['conversation_id']}). Sua última mensagem: \"{$signal['excerpt']}\"]";
        }

        if (! $signal['from_me']) {
            $markers[] = "[CLIENTE AGUARDANDO SUA RESPOSTA há {$hours}h (conversa #{$signal['conversation_id']}). Última mensagem do cliente: \"{$signal['excerpt']}\"]";
        }

        return $markers;
    }

    /**
     * @return Collection<int, Task>
     */
    private function habitTasks(Company $company): Collection
    {
        return Task::allTenants()
            ->where('company_id', $company->id)
            ->where('is_continuous', true)
            ->get();
    }

    /**
     * @param  Collection<int, Task>  $habits
     */
    private function habitsSection(Collection $habits): ?string
    {
        if ($habits->isEmpty()) {
            return null;
        }

        $lines = $habits->map(function (Task $task): string {
            $streak = $task->currentStreak();

            return '- [tarefa #'.$task->id.'] '.$task->title
                .' - '.($task->isDoneToday() ? 'JÁ FEITO hoje' : 'pendente hoje')
                .($streak > 0 ? " - sequência de {$streak} dia(s), não deixe quebrar" : '');
        });

        return "## Hábitos diários (daily checks)\n".$lines->implode("\n");
    }

    /**
     * @return Collection<int, WhatsappConversation>
     */
    private function recentConversations(Company $company, CarbonImmutable $today, int $maxConversations): Collection
    {
        $windowDays = max(1, (int) config('services.planner.conversation_window_days', 14));

        return WhatsappConversation::allTenants()
            ->where('company_id', $company->id)
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '>=', $today->subDays($windowDays)->utc())
            ->with(['client'])
            ->orderByDesc('last_message_at')
            ->take(max(1, $maxConversations))
            ->get();
    }

    /**
     * @param  Collection<int, WhatsappConversation>  $conversations
     */
    private function conversationsSection(Collection $conversations, CarbonImmutable $today, int $maxMessages): ?string
    {
        if ($conversations->isEmpty()) {
            return null;
        }

        $messageChars = max(40, (int) config('services.planner.message_chars', 220));
        $timezone = $today->timezone;

        $blocks = $conversations->map(function (WhatsappConversation $conversation) use ($maxMessages, $messageChars, $timezone): string {
            $header = '### Conversa #'.$conversation->id.' - '.$conversation->display_name
                .($conversation->client ? ' (cliente: '.$conversation->client->name.')' : '')
                .($conversation->is_group ? ' [grupo]' : '')
                .((int) $conversation->unread_count > 0 ? ' - '.$conversation->unread_count.' não lida(s)' : '');

            $messages = WhatsappMessage::allTenants()
                ->where('company_id', $conversation->company_id)
                ->where('whatsapp_conversation_id', $conversation->id)
                ->orderByDesc('sent_at')
                ->orderByDesc('id')
                ->take($maxMessages)
                ->get()
                ->reverse()
                ->values();

            $lines = $messages->map(function (WhatsappMessage $message) use ($messageChars, $timezone): string {
                $who = $message->from_me ? 'Luiz' : 'Cliente';
                $when = $message->sent_at?->copy()->setTimezone($timezone)->format('d/m H:i') ?? '-';
                $text = trim((string) $message->text);

                if ($text === '') {
                    $text = '['.($message->message_type ?: 'mensagem').' sem texto]';
                }

                return "{$who} ({$when}): ".Str::limit($text, $messageChars);
            });

            return $header."\n".$lines->implode("\n");
        });

        return "## Conversas recentes de WhatsApp com clientes\n".$blocks->implode("\n\n");
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function relevantInvoices(Company $company, CarbonImmutable $today): Collection
    {
        $pending = Invoice::allTenants()
            ->where('company_id', $company->id)
            ->with('client')
            ->pending()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->addDays(7)->toDateString())
            ->orderBy('due_date')
            ->get();

        $staleDrafts = Invoice::allTenants()
            ->where('company_id', $company->id)
            ->with('client')
            ->where('status', InvoiceStatusEnum::DRAFT->value)
            ->where('created_at', '<', $today->subDays(3)->utc())
            ->get();

        return $pending->concat($staleDrafts)->unique('id')->values();
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    private function invoicesSection(Collection $invoices, CarbonImmutable $today, string $dateFormat): ?string
    {
        if ($invoices->isEmpty()) {
            return null;
        }

        $lines = $invoices->map(function (Invoice $invoice) use ($today, $dateFormat): string {
            $clientName = $invoice->client?->name ?? 'Sem cliente';

            if ($invoice->status === InvoiceStatusEnum::DRAFT) {
                $status = 'RASCUNHO antigo, avalie emitir';
            } elseif ($invoice->due_date?->lt($today)) {
                $status = 'VENCIDA há '.(int) $invoice->due_date->diffInDays($today).' dia(s)';
            } else {
                $status = 'vence em '.$invoice->due_date?->format($dateFormat);
            }

            return "- [fatura #{$invoice->id}] #{$invoice->number} {$invoice->title} (cliente: {$clientName}) - {$status}";
        });

        return "## Faturas que precisam de atenção\n".$lines->implode("\n");
    }

    private function unbilledWorkSection(Company $company): ?string
    {
        $byClient = WorkSession::allTenants()
            ->where('company_id', $company->id)
            ->where('billable', true)
            ->whereNull('invoice_item_id')
            ->whereNotNull('client_id')
            ->with('client')
            ->get()
            ->groupBy('client_id')
            ->map(fn (Collection $sessions) => [
                'client' => $sessions->first()->client?->name ?? 'Cliente removido',
                'minutes' => (int) $sessions->sum('duration'),
            ])
            ->filter(fn (array $row): bool => $row['minutes'] >= 60)
            ->sortByDesc('minutes');

        if ($byClient->isEmpty()) {
            return null;
        }

        $lines = $byClient->map(
            fn (array $row): string => '- '.$row['client'].': '.PejotaHelper::formatDuration($row['minutes']).' faturáveis ainda não faturadas'
        );

        return "## Trabalho faturável não faturado (sinal de hora de cobrar)\n".$lines->values()->implode("\n");
    }

    /**
     * @return Collection<int, Contract>
     */
    private function endingContracts(Company $company, CarbonImmutable $today): Collection
    {
        $clientIds = Client::allTenants()->where('company_id', $company->id)->pluck('id');

        return Contract::query()
            ->whereIn('client_id', $clientIds)
            ->whereNotNull('end_at')
            ->whereDate('end_at', '>=', $today->toDateString())
            ->whereDate('end_at', '<=', $today->addDays(30)->toDateString())
            ->with('client')
            ->orderBy('end_at')
            ->get();
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     */
    private function contractsSection(Collection $contracts, CarbonImmutable $today, string $dateFormat): ?string
    {
        if ($contracts->isEmpty()) {
            return null;
        }

        $lines = $contracts->map(function (Contract $contract) use ($today, $dateFormat): string {
            $endAt = CarbonImmutable::parse($contract->end_at);

            return "- [contrato #{$contract->id}] {$contract->title} (cliente: ".($contract->client?->name ?? '-').') - termina em '
                .$endAt->format($dateFormat).' ('.(int) $today->diffInDays($endAt).' dia(s)), avalie renovação';
        });

        return "## Contratos terminando em até 30 dias\n".$lines->implode("\n");
    }

    private function subscriptionsSection(Company $company, CarbonImmutable $today, string $dateFormat): ?string
    {
        $subscriptions = Subscription::allTenants()
            ->where('company_id', $company->id)
            ->where('status', SubscriptionStatusEnum::TRIAL->value)
            ->whereNotNull('trial_ends_at')
            ->whereDate('trial_ends_at', '<=', $today->addDays(15)->toDateString())
            ->orderBy('trial_ends_at')
            ->get();

        if ($subscriptions->isEmpty()) {
            return null;
        }

        $lines = $subscriptions->map(function ($subscription) use ($dateFormat): string {
            return "- {$subscription->service}: trial termina em ".CarbonImmutable::parse($subscription->trial_ends_at)->format($dateFormat).', decidir se mantém ou cancela';
        });

        return "## Assinaturas com trial acabando\n".$lines->implode("\n");
    }

    private function notesSection(Company $company, CarbonImmutable $today): ?string
    {
        $notes = Note::allTenants()
            ->where('company_id', $company->id)
            ->where('updated_at', '>=', $today->subDays(7)->utc())
            ->with(['client', 'project'])
            ->latest('updated_at')
            ->take(10)
            ->get();

        if ($notes->isEmpty()) {
            return null;
        }

        $lines = $notes->map(function (Note $note): string {
            $about = collect([$note->client?->name, $note->project?->name])->filter()->implode(' / ');

            return '- '.$note->title.($about !== '' ? " ({$about})" : '');
        });

        return "## Notas recentes (últimos 7 dias)\n".$lines->implode("\n");
    }

    private function analysesSection(Company $company): ?string
    {
        $analyses = ClientAiAnalysis::allTenants()
            ->where('company_id', $company->id)
            ->with('client')
            ->latest()
            ->get()
            ->unique('client_id');

        $lines = $analyses->map(function (ClientAiAnalysis $analysis): string {
            return '- '.($analysis->client?->name ?? 'Cliente removido').': '.Str::limit(strip_tags((string) $analysis->content), 200);
        });

        $contexts = Client::allTenants()
            ->where('company_id', $company->id)
            ->whereNotNull('ai_context')
            ->where('ai_context', '!=', '')
            ->get()
            ->map(fn (Client $client): string => '- '.$client->name.' (contexto fixo): '.Str::limit(strip_tags((string) $client->ai_context), 200));

        $all = $lines->concat($contexts);

        if ($all->isEmpty()) {
            return null;
        }

        return "## Contexto e análises de clientes\n".$all->implode("\n");
    }

    private function freshnessSection(Company $company, CarbonImmutable $today): string
    {
        $lastSyncedAt = WhatsappMessage::allTenants()
            ->where('company_id', $company->id)
            ->max('sent_at');

        if ($lastSyncedAt === null) {
            return "## Frescor dos dados\nNenhuma mensagem de WhatsApp sincronizada até agora; não afirme bloqueios baseados em conversas.";
        }

        $lastSyncedAt = CarbonImmutable::parse($lastSyncedAt);
        $hours = (int) $lastSyncedAt->diffInHours($today->setTimeFromTimeString(now($today->timezone)->format('H:i:s')));

        $line = 'Mensagem de WhatsApp mais recente registrada: '.$lastSyncedAt->setTimezone($today->timezone)->format('d/m/Y H:i').'.';

        if ($hours > 24) {
            $line .= ' ATENÇÃO: as conversas podem estar desatualizadas (mais de 24h sem mensagem nova registrada); seja mais cauteloso ao afirmar que um cliente está em silêncio.';
        }

        return "## Frescor dos dados\n".$line;
    }
}
