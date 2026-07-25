<?php

namespace App\Jobs;

use App\Enums\DailyPlanModeEnum;
use App\Enums\DailyPlanStatusEnum;
use App\Helpers\PejotaHelper;
use App\Models\Company;
use App\Models\DailyPlan;
use App\Services\Planner\DailyPlanGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Throwable;

class GenerateDailyPlan implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Up to three AI CLI attempts of services.planner.timeout each (max
     * effort, high effort, global default), plus context building slack.
     */
    public int $timeout = 2000;

    public int $tries = 1;

    public function __construct(
        public readonly Company $company,
        public readonly string $date,
        public readonly string $mode = DailyPlanModeEnum::FULL->value,
    ) {}

    public function uniqueId(): string
    {
        return $this->company->id.'-'.$this->date;
    }

    public function handle(DailyPlanGenerator $generator): void
    {
        if ($this->company->user_id) {
            Auth::onceUsingId($this->company->user_id);
        }

        /**
         * The date string must be parsed in the user's timezone: parsed as
         * UTC midnight, converting to a negative-offset timezone later
         * shifts the plan back one calendar day (wrong weekday capacity).
         */
        $generator->generate(
            $this->company,
            CarbonImmutable::parse($this->date, PejotaHelper::getUserTimeZoneOrDefault())->startOfDay(),
            DailyPlanModeEnum::from($this->mode),
        );
    }

    public function failed(?Throwable $exception): void
    {
        DailyPlan::allTenants()
            ->where('company_id', $this->company->id)
            ->whereDate('plan_date', $this->date)
            ->where('status', DailyPlanStatusEnum::GENERATING->value)
            ->update([
                'status' => DailyPlanStatusEnum::FAILED->value,
                'failure_reason' => $exception?->getMessage() ?? __('The plan generation job failed.'),
            ]);
    }
}
