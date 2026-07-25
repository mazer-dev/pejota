<?php

namespace Tests\Feature\Planner;

use App\Enums\CompanySettingsEnum;
use App\Enums\DailyPlanModeEnum;
use App\Jobs\GenerateDailyPlan;
use App\Models\Company;
use App\Models\DailyPlan;
use App\Models\User;
use App\Services\Planner\DailyPlanGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GenerateDailyPlanJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_plan_date_is_parsed_in_the_user_timezone(): void
    {
        $user = User::factory()->create();
        $user->company->settings()->set(
            CompanySettingsEnum::LOCALIZATION_TIMEZONE->value,
            'America/Sao_Paulo',
        );

        $generator = Mockery::mock(DailyPlanGenerator::class);
        $generator->shouldReceive('generate')
            ->once()
            ->withArgs(function (Company $company, CarbonImmutable $date, DailyPlanModeEnum $mode): bool {
                return $date->toDateString() === '2026-07-25'
                    && $date->timezone->getName() === 'America/Sao_Paulo';
            })
            ->andReturn(new DailyPlan);
        $this->instance(DailyPlanGenerator::class, $generator);

        $job = new GenerateDailyPlan($user->company->fresh(), '2026-07-25', DailyPlanModeEnum::FULL->value);
        $job->handle(app(DailyPlanGenerator::class));
    }
}
