<?php

namespace Tests\Feature\Planner;

use App\Enums\CompanySettingsEnum;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Planner\PlannerCapacity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_mon_to_fri_8h_sat_4h_sun_3h(): void
    {
        $user = User::factory()->create();
        $capacity = PlannerCapacity::forCompany($user->company);

        $monday = CarbonImmutable::parse('2026-07-20');
        $saturday = CarbonImmutable::parse('2026-07-25');
        $sunday = CarbonImmutable::parse('2026-07-26');

        $this->assertSame(480, $capacity->dayCapacityMinutes($monday));
        $this->assertSame(240, $capacity->dayCapacityMinutes($saturday));
        $this->assertSame(180, $capacity->dayCapacityMinutes($sunday));
        $this->assertSame((5 * 8 + 4 + 3) * 60, $capacity->weeklyMinutes());
        $this->assertTrue($capacity->isWorkDay($sunday));
    }

    public function test_configured_day_hours_override_defaults_and_zero_means_day_off(): void
    {
        $user = User::factory()->create();
        $user->company->settings()->set(CompanySettingsEnum::PLANNER_DAY_HOURS->value, [
            1 => 6, 2 => 6, 3 => 6, 4 => 6, 5 => 6, 6 => 0, 7 => 0,
        ]);

        $capacity = PlannerCapacity::forCompany($user->company->fresh());

        $monday = CarbonImmutable::parse('2026-07-20');
        $saturday = CarbonImmutable::parse('2026-07-25');

        $this->assertSame(360, $capacity->dayCapacityMinutes($monday));
        $this->assertFalse($capacity->isWorkDay($saturday));
        $this->assertSame(0, $capacity->dayCapacityMinutes($saturday));
    }

    public function test_worked_minutes_sum_work_sessions_of_the_day_and_week(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $today = CarbonImmutable::parse('2026-07-22 12:00:00');

        WorkSession::create([
            'company_id' => $companyId,
            'start' => $today->setTime(9, 0),
            'end' => $today->setTime(10, 30),
            'is_running' => false,
        ]);

        WorkSession::create([
            'company_id' => $companyId,
            'start' => $today->subDays(1)->setTime(9, 0),
            'end' => $today->subDays(1)->setTime(10, 0),
            'is_running' => false,
        ]);

        WorkSession::create([
            'company_id' => $companyId,
            'start' => $today->subWeeks(2)->setTime(9, 0),
            'end' => $today->subWeeks(2)->setTime(17, 0),
            'is_running' => false,
        ]);

        $capacity = PlannerCapacity::forCompany($user->company);

        $this->assertSame(90, $capacity->workedOnDayMinutes($today));
        $this->assertSame(150, $capacity->workedThisWeekMinutes($today));
        $this->assertSame(480 - 90, $capacity->remainingTodayMinutes($today));
    }
}
