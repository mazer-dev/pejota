<?php

namespace App\Console\Commands;

use App\Enums\DailyPlanModeEnum;
use App\Helpers\PejotaHelper;
use App\Models\Company;
use App\Models\DailyPlan;
use App\Services\Planner\DailyPlanGenerator;
use App\Services\Planner\DailyPlanWhatsappNotifier;
use App\Services\Planner\PlannerCapacity;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SendDailyPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pj:daily-plan:send
        {--company= : Restrict to a single company ID}
        {--force : Send again even when the plan was already delivered today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia o plano do dia pelo WhatsApp do assistente aos números configurados (gera na hora se ainda não existir).';

    public function handle(DailyPlanWhatsappNotifier $notifier): int
    {
        $companies = filled($this->option('company'))
            ? Company::query()->where('id', $this->option('company'))->get()
            : Company::all();

        if ($companies->isEmpty()) {
            $this->warn('Nenhuma empresa encontrada.');

            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            $this->sendForCompany($company, $notifier);
        }

        return self::SUCCESS;
    }

    private function sendForCompany(Company $company, DailyPlanWhatsappNotifier $notifier): void
    {
        if ($company->user_id) {
            Auth::onceUsingId($company->user_id);
        }

        $today = CarbonImmutable::now(PejotaHelper::getUserTimeZoneOrDefault())->startOfDay();

        $plan = DailyPlan::allTenants()
            ->where('company_id', $company->id)
            ->forDate($today)
            ->with(['items', 'company'])
            ->first();

        if ($plan === null || $plan->isFailed()) {
            $plan = $this->generateBestEffort($company, $today) ?? $plan;
        }

        if ($plan === null || ! $plan->isReady()) {
            $this->warn("{$company->name}: sem plano pronto para enviar hoje.");

            return;
        }

        $sent = $notifier->send($plan->load(['items', 'company']), force: (bool) $this->option('force'));

        $this->line($sent
            ? "{$company->name}: plano do dia enviado pelo WhatsApp."
            : "{$company->name}: envio pulado (já enviado, entrega desligada ou WhatsApp indisponível).");
    }

    /**
     * The 08:00 delivery must not stay silent just because the 07:00
     * generation failed or never ran: try generating inline once, and give
     * up quietly (reporting) if the AI is unavailable.
     */
    private function generateBestEffort(Company $company, CarbonImmutable $today): ?DailyPlan
    {
        $this->line("{$company->name}: plano de hoje ausente ou com falha, tentando gerar agora...");

        try {
            $capacity = PlannerCapacity::forCompany($company);
            $mode = $capacity->isWorkDay($today) ? DailyPlanModeEnum::FULL : DailyPlanModeEnum::LIGHT;

            return app(DailyPlanGenerator::class)->generate($company, $today, $mode)->load(['items', 'company']);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
