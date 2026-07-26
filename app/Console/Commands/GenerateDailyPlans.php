<?php

namespace App\Console\Commands;

use App\Enums\CompanySettingsEnum;
use App\Enums\DailyPlanModeEnum;
use App\Helpers\PejotaHelper;
use App\Jobs\GenerateDailyPlan;
use App\Models\Company;
use App\Models\DailyPlan;
use App\Services\Planner\DailyPlanGenerator;
use App\Services\Planner\PlannerCapacity;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class GenerateDailyPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pj:daily-plan
        {--company= : Restrict to a single company ID}
        {--date= : Plan date (Y-m-d, defaults to today in the company timezone)}
        {--force : Regenerate even when a ready plan already exists}
        {--minutes= : Plan for this many minutes of EXTRA work now, ignoring the day being over}
        {--sync : Generate inline instead of dispatching the queue job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera o plano do dia com IA para cada empresa (completo em dia de trabalho, leve em dia de folga).';

    public function handle(): int
    {
        $companies = filled($this->option('company'))
            ? Company::query()->where('id', $this->option('company'))->get()
            : Company::all();

        if ($companies->isEmpty()) {
            $this->warn('Nenhuma empresa encontrada.');

            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            $this->planCompany($company);
        }

        return self::SUCCESS;
    }

    private function planCompany(Company $company): void
    {
        if ($company->user_id) {
            Auth::onceUsingId($company->user_id);
        }

        $scheduledRun = blank($this->option('company'));

        if ($scheduledRun && ! $this->autoGenerateEnabled($company)) {
            $this->line("{$company->name}: geração automática desligada, pulando.");

            return;
        }

        $date = filled($this->option('date'))
            ? CarbonImmutable::parse((string) $this->option('date'), PejotaHelper::getUserTimeZoneOrDefault())->startOfDay()
            : CarbonImmutable::now(PejotaHelper::getUserTimeZoneOrDefault())->startOfDay();

        $override = filled($this->option('minutes')) ? max(1, (int) $this->option('minutes')) : null;

        $existing = DailyPlan::allTenants()
            ->where('company_id', $company->id)
            ->forDate($date)
            ->first();

        if ($existing?->isReady() && ! $this->option('force') && $override === null) {
            $this->line("{$company->name}: plano de {$date->toDateString()} já gerado, pulando (use --force para regerar).");

            return;
        }

        $capacity = PlannerCapacity::forCompany($company);
        $mode = ($override !== null || $capacity->isWorkDay($date))
            ? DailyPlanModeEnum::FULL
            : DailyPlanModeEnum::LIGHT;

        if ($this->option('sync')) {
            $plan = app(DailyPlanGenerator::class)->generate($company, $date, $mode, $override);

            $this->line("{$company->name}: plano {$plan->status->value} ({$plan->mode->value}) com ".$plan->items()->count().' item(ns).');

            if ($plan->isFailed()) {
                $this->error($plan->failure_reason ?? 'Falha desconhecida.');
            }

            return;
        }

        GenerateDailyPlan::dispatch($company, $date->toDateString(), $mode->value, $override);

        $this->line("{$company->name}: geração do plano de {$date->toDateString()} ({$mode->value}) enviada para a fila.");
    }

    private function autoGenerateEnabled(Company $company): bool
    {
        return $company->settings()->get(CompanySettingsEnum::PLANNER_AUTO_GENERATE->value) !== false;
    }
}
