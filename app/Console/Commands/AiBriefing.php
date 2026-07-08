<?php

namespace App\Console\Commands;

use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\ClientAiAnalysis;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\Context\PromptGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AiBriefing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pj:ai-briefing {--company= : Restrict the briefing to a single company ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cruza tarefas, faturas e conversas de todos os clientes e gera um briefing priorizado do dia.';

    public function handle(AiCliRunner $cliRunner): int
    {
        $companies = filled($this->option('company'))
            ? Company::query()->where('id', $this->option('company'))->get()
            : Company::all();

        if ($companies->isEmpty()) {
            $this->warn('Nenhuma empresa encontrada.');

            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            $this->briefCompany($company, $cliRunner);
        }

        return self::SUCCESS;
    }

    private function briefCompany(Company $company, AiCliRunner $cliRunner): void
    {
        // Reuses PejotaHelper (timezone/date format/locale), which reads
        // settings from the currently authenticated user's company. Console
        // commands run with no authenticated user, so the company owner is
        // logged in for the lifetime of this iteration only.
        if ($company->user_id) {
            Auth::onceUsingId($company->user_id);
        }

        $context = $this->collectContext($company);

        if ($context === '') {
            $this->line("=== {$company->name}: nada relevante para hoje. ===");
            $this->newLine();

            return;
        }

        $briefing = trim($cliRunner->complete($this->prompt($context)));

        $this->line("=== Briefing do dia - {$company->name} ===");
        $this->line($briefing);
        $this->newLine();
    }

    private function collectContext(Company $company): string
    {
        $dateFormat = PejotaHelper::getUserDateFormatOrDefault();
        $today = now(PejotaHelper::getUserTimeZoneOrDefault())->startOfDay();

        $sections = [
            $this->tasksSection($company, $today, $dateFormat),
            $this->waitingClientSection($company),
            $this->invoicesSection($company, $today, $dateFormat),
            $this->conversationsSection($company, $dateFormat),
            $this->analysesSection($company, $dateFormat),
        ];

        return collect($sections)->filter()->implode("\n\n");
    }

    /**
     * Open tasks tagged "aguardando-cliente", enriched with how long the
     * client has been silent and how long since our own last message, so
     * the briefing can suggest a polite follow-up when it makes sense.
     */
    private function waitingClientSection(Company $company): ?string
    {
        $tasks = Task::allTenants()
            ->where('company_id', $company->id)
            ->with('client')
            ->opened()
            ->withAnyTags([Task::TAG_WAITING_CLIENT])
            ->get();

        if ($tasks->isEmpty()) {
            return null;
        }

        $now = now(PejotaHelper::getUserTimeZoneOrDefault());

        $lines = $tasks->map(function (Task $task) use ($company, $now): string {
            $clientName = $task->client?->name ?? 'Sem cliente';
            $line = "- [{$clientName}] {$task->title}";

            if ($task->client_id) {
                $lastFromClient = $this->lastMessageAt($company, $task->client_id, fromMe: false);
                $lastFromUs = $this->lastMessageAt($company, $task->client_id, fromMe: true);

                $line .= $lastFromClient
                    ? ' - última mensagem do cliente há '.(int) $lastFromClient->diffInDays($now).' dia(s)'
                    : ' - sem mensagem do cliente registrada';

                $line .= $lastFromUs
                    ? '; sua última mensagem há '.(int) $lastFromUs->diffInDays($now).' dia(s)'
                    : '; você ainda não mandou mensagem';
            }

            return $line;
        });

        return "Tarefas aguardando o cliente (avalie sugerir follow-up educado quando o silêncio for longo):\n".$lines->implode("\n");
    }

    private function lastMessageAt(Company $company, int $clientId, bool $fromMe): ?Carbon
    {
        $sentAt = WhatsappMessage::allTenants()
            ->where('company_id', $company->id)
            ->where('client_id', $clientId)
            ->where('from_me', $fromMe)
            ->max('sent_at');

        return $sentAt ? Carbon::parse($sentAt) : null;
    }

    private function tasksSection(Company $company, $today, string $dateFormat): ?string
    {
        $tasks = Task::allTenants()
            ->where('company_id', $company->id)
            ->with(['client', 'status'])
            ->opened()
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->get();

        if ($tasks->isEmpty()) {
            return null;
        }

        $lines = $tasks->map(function (Task $task) use ($today, $dateFormat): string {
            $clientName = $task->client?->name ?? 'Sem cliente';
            $status = $task->due_date->lt($today)
                ? 'ATRASADA há '.(int) $task->due_date->diffInDays($today).' dia(s)'
                : 'vence em '.$task->due_date->format($dateFormat);

            return "- [{$clientName}] {$task->title} - {$status}";
        });

        return "Tarefas abertas com vencimento:\n".$lines->implode("\n");
    }

    private function invoicesSection(Company $company, $today, string $dateFormat): ?string
    {
        $invoices = Invoice::allTenants()
            ->where('company_id', $company->id)
            ->with('client')
            ->pending()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->copy()->addDays(7)->toDateString())
            ->orderBy('due_date')
            ->get();

        if ($invoices->isEmpty()) {
            return null;
        }

        $lines = $invoices->map(function (Invoice $invoice) use ($today, $dateFormat): string {
            $clientName = $invoice->client?->name ?? 'Sem cliente';
            $status = $invoice->due_date->lt($today)
                ? 'VENCIDA há '.(int) $invoice->due_date->diffInDays($today).' dia(s)'
                : 'vence em '.$invoice->due_date->format($dateFormat);

            return "- [{$clientName}] #{$invoice->number} {$invoice->title} - {$status}";
        });

        return "Faturas vencidas ou a vencer em 7 dias:\n".$lines->implode("\n");
    }

    private function conversationsSection(Company $company, string $dateFormat): ?string
    {
        $conversations = WhatsappConversation::allTenants()
            ->where('company_id', $company->id)
            ->with(['client', 'latestMessage'])
            ->whereHas('latestMessage', fn ($query) => $query->where('from_me', false))
            ->get();

        if ($conversations->isEmpty()) {
            return null;
        }

        $lines = $conversations->map(function (WhatsappConversation $conversation) use ($dateFormat): string {
            $who = $conversation->client?->name ?? $conversation->display_name;
            $date = $conversation->latestMessage?->sent_at?->format($dateFormat.' H:i') ?? '-';

            return "- {$who}: última mensagem do cliente em {$date}, ainda sem resposta.";
        });

        return "Conversas de WhatsApp aguardando resposta:\n".$lines->implode("\n");
    }

    private function analysesSection(Company $company, string $dateFormat): ?string
    {
        $clientIds = Client::allTenants()->where('company_id', $company->id)->pluck('id');

        $analyses = ClientAiAnalysis::allTenants()
            ->where('company_id', $company->id)
            ->whereIn('client_id', $clientIds)
            ->with('client')
            ->latest()
            ->get()
            ->unique('client_id');

        if ($analyses->isEmpty()) {
            return null;
        }

        $lines = $analyses->map(function (ClientAiAnalysis $analysis) use ($dateFormat): string {
            $clientName = $analysis->client?->name ?? 'Cliente removido';
            $date = $analysis->created_at?->format($dateFormat) ?? '-';

            return "- {$clientName} (análise de {$date}): ".Str::limit(strip_tags($analysis->content), 200);
        });

        return "Últimas análises de clientes:\n".$lines->implode("\n");
    }

    private function prompt(string $context): string
    {
        $instructions = implode("\n", [
            'Você monta o briefing priorizado do dia para o Luiz, um freelancer solo que usa o PeJota para gerenciar clientes, tarefas, faturas e conversas de WhatsApp.',
            'Responda em português do Brasil, em tópicos objetivos, priorizando o que é mais urgente primeiro (atrasos, vencimentos, clientes esperando resposta).',
            'Use apenas informações do contexto. Não invente prazos, valores ou decisões.',
            PromptGuard::instruction(),
        ]);

        return implode("\n\n", [
            $instructions,
            "Contexto disponível:\n".PromptGuard::wrap($context),
            'Gere o briefing do dia agora.',
        ]);
    }
}
