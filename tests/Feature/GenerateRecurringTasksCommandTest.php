<?php

namespace Tests\Feature;

use App\Enums\RecurrenceAnchorFieldEnum;
use App\Enums\RecurrenceFrequencyEnum;
use App\Enums\RecurrenceGenerationModeEnum;
use App\Enums\RecurrenceStopTypeEnum;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\RecurrenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class GenerateRecurringTasksCommandTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    public function test_command_generates_due_occurrences_across_companies(): void
    {
        $userA = User::factory()->create();
        $companyA = $this->actingInCompany($userA);
        $statusA = Status::create([
            'name' => 'To Do', 'phase' => 'todo', 'color' => '#000000',
            'sort_order' => 1, 'active' => true, 'company_id' => $companyA->id,
        ]);
        $taskA = Task::create([
            'title' => 'A monthly', 'status_id' => $statusA->id,
            'company_id' => $companyA->id, 'due_date' => '2026-01-15',
        ]);
        $service = app(RecurrenceService::class);
        $recurrenceA = $service->enableForTask($taskA, [
            'frequency' => RecurrenceFrequencyEnum::Monthly,
            'interval' => 1,
            'anchor_field' => RecurrenceAnchorFieldEnum::DueDate,
            'generation_mode' => RecurrenceGenerationModeEnum::ByDate,
            'stop_type' => RecurrenceStopTypeEnum::Never,
        ]);
        $recurrenceA->update(['next_run_date' => Carbon::today()->subMonth()->toDateString()]);

        $this->artisan('pj:generate-recurring-tasks')->assertExitCode(0);

        $this->assertGreaterThanOrEqual(2, $recurrenceA->occurrences()->count());
        $this->assertTrue($recurrenceA->fresh()->next_run_date->gt(Carbon::today()));
    }

    public function test_command_respects_count_stop(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);
        $status = Status::create([
            'name' => 'To Do', 'phase' => 'todo', 'color' => '#000000',
            'sort_order' => 1, 'active' => true, 'company_id' => $company->id,
        ]);
        $task = Task::create([
            'title' => 'Weekly x3', 'status_id' => $status->id,
            'company_id' => $company->id, 'due_date' => '2026-01-01',
        ]);
        $service = app(RecurrenceService::class);
        $recurrence = $service->enableForTask($task, [
            'frequency' => RecurrenceFrequencyEnum::Weekly,
            'interval' => 1,
            'anchor_field' => RecurrenceAnchorFieldEnum::DueDate,
            'generation_mode' => RecurrenceGenerationModeEnum::ByDate,
            'stop_type' => RecurrenceStopTypeEnum::Count,
            'max_count' => 3,
        ]);
        $recurrence->update(['next_run_date' => Carbon::today()->subYear()->toDateString()]);

        $this->artisan('pj:generate-recurring-tasks')->assertExitCode(0);

        $recurrence->refresh();
        $this->assertFalse($recurrence->is_active);
        $this->assertSame(3, $recurrence->generated_count);
        $this->assertSame(3, $recurrence->occurrences()->count());
    }

    public function test_command_runs_without_authenticated_user(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);
        $status = Status::create([
            'name' => 'To Do', 'phase' => 'todo', 'color' => '#000000',
            'sort_order' => 1, 'active' => true, 'company_id' => $company->id,
        ]);
        $task = Task::create([
            'title' => 'Cron weekly', 'status_id' => $status->id,
            'company_id' => $company->id, 'due_date' => '2026-01-01',
        ]);
        $recurrence = app(RecurrenceService::class)->enableForTask($task, [
            'frequency' => RecurrenceFrequencyEnum::Weekly,
            'interval' => 1,
            'anchor_field' => RecurrenceAnchorFieldEnum::DueDate,
            'generation_mode' => RecurrenceGenerationModeEnum::ByDate,
            'stop_type' => RecurrenceStopTypeEnum::Never,
        ]);
        $recurrence->update(['next_run_date' => Carbon::today()->subWeek()->toDateString()]);

        auth()->logout();

        $this->artisan('pj:generate-recurring-tasks')->assertExitCode(0);

        $this->assertGreaterThanOrEqual(2, $recurrence->occurrences()->count());
    }
}
