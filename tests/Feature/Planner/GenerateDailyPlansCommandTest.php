<?php

namespace Tests\Feature\Planner;

use App\Enums\CompanySettingsEnum;
use App\Enums\DailyPlanModeEnum;
use App\Enums\DailyPlanStatusEnum;
use App\Jobs\GenerateDailyPlan;
use App\Models\DailyPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class GenerateDailyPlansCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_the_generation_job_in_full_mode_on_a_work_day(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->company->settings()->set(CompanySettingsEnum::PLANNER_DAY_HOURS->value, [
            1 => 8, 2 => 8, 3 => 8, 4 => 8, 5 => 8, 6 => 8, 7 => 8,
        ]);

        $this->artisan('pj:daily-plan', ['--company' => $user->company->id])
            ->assertSuccessful();

        Bus::assertDispatched(GenerateDailyPlan::class, function (GenerateDailyPlan $job) use ($user): bool {
            return $job->company->id === $user->company->id
                && $job->mode === DailyPlanModeEnum::FULL->value;
        });
    }

    public function test_dispatches_in_light_mode_on_a_day_off(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->company->settings()->set(CompanySettingsEnum::PLANNER_DAY_HOURS->value, [
            1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0,
        ]);

        $this->artisan('pj:daily-plan', ['--company' => $user->company->id])
            ->assertSuccessful();

        Bus::assertDispatched(GenerateDailyPlan::class, function (GenerateDailyPlan $job): bool {
            return $job->mode === DailyPlanModeEnum::LIGHT->value;
        });
    }

    public function test_scheduled_run_skips_companies_with_auto_generate_disabled(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->company->settings()->set(CompanySettingsEnum::PLANNER_AUTO_GENERATE->value, false);

        $this->artisan('pj:daily-plan')
            ->expectsOutputToContain('geração automática desligada')
            ->assertSuccessful();

        Bus::assertNotDispatched(GenerateDailyPlan::class);
    }

    public function test_targeted_run_ignores_the_auto_generate_setting(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $user->company->settings()->set(CompanySettingsEnum::PLANNER_AUTO_GENERATE->value, false);

        $this->artisan('pj:daily-plan', ['--company' => $user->company->id])
            ->assertSuccessful();

        Bus::assertDispatched(GenerateDailyPlan::class);
    }

    public function test_does_not_regenerate_a_ready_plan_without_force(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        DailyPlan::create([
            'company_id' => $user->company->id,
            'plan_date' => now()->toDateString(),
            'mode' => DailyPlanModeEnum::FULL,
            'status' => DailyPlanStatusEnum::READY,
        ]);

        $this->artisan('pj:daily-plan', ['--company' => $user->company->id])
            ->expectsOutputToContain('já gerado')
            ->assertSuccessful();

        Bus::assertNotDispatched(GenerateDailyPlan::class);

        $this->artisan('pj:daily-plan', ['--company' => $user->company->id, '--force' => true])
            ->assertSuccessful();

        Bus::assertDispatched(GenerateDailyPlan::class);
    }
}
