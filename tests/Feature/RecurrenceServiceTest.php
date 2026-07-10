<?php

namespace Tests\Feature;

use App\Enums\RecurrenceAnchorFieldEnum;
use App\Enums\RecurrenceFrequencyEnum;
use App\Enums\RecurrenceGenerationModeEnum;
use App\Enums\RecurrenceStopTypeEnum;
use App\Models\Company;
use App\Models\Status;
use App\Models\Task;
use App\Models\TaskRecurrence;
use App\Models\User;
use App\Services\RecurrenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class RecurrenceServiceTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private Company $company;

    public function test_recurrence_enums_have_expected_values(): void
    {
        $this->assertSame('weekly', RecurrenceFrequencyEnum::Weekly->value);
        $this->assertSame('monthly', RecurrenceFrequencyEnum::Monthly->value);
        $this->assertSame('yearly', RecurrenceFrequencyEnum::Yearly->value);

        $this->assertSame('due_date', RecurrenceAnchorFieldEnum::DueDate->value);
        $this->assertSame('planned_end', RecurrenceAnchorFieldEnum::PlannedEnd->value);
        $this->assertSame('both', RecurrenceAnchorFieldEnum::Both->value);

        $this->assertSame('by_date', RecurrenceGenerationModeEnum::ByDate->value);
        $this->assertSame('on_completion', RecurrenceGenerationModeEnum::OnCompletion->value);

        $this->assertSame('never', RecurrenceStopTypeEnum::Never->value);
        $this->assertSame('until_date', RecurrenceStopTypeEnum::UntilDate->value);
        $this->assertSame('count', RecurrenceStopTypeEnum::Count->value);
    }

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $this->company = $this->actingInCompany($user);

        return $user;
    }

    private function makeStatus(User $user): Status
    {
        return Status::create([
            'name' => 'To Do',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_task_recurrence_relations(): void
    {
        $user = $this->makeUser();
        $status = $this->makeStatus($user);

        $template = Task::create([
            'title' => 'Template',
            'status_id' => $status->id,
            'company_id' => $this->company->id,
            'is_recurrence_template' => true,
        ]);

        $recurrence = TaskRecurrence::create([
            'company_id' => $this->company->id,
            'task_id' => $template->id,
            'frequency' => RecurrenceFrequencyEnum::Monthly,
            'generated_count' => 1,
        ]);

        $occurrence = Task::create([
            'title' => 'Occurrence',
            'status_id' => $status->id,
            'company_id' => $this->company->id,
            'recurrence_id' => $recurrence->id,
        ]);

        $this->assertTrue($recurrence->template->is($template));
        $this->assertTrue($recurrence->occurrences->first()->is($occurrence));
        $this->assertTrue($occurrence->recurrence->is($recurrence));
        $this->assertSame(RecurrenceFrequencyEnum::Monthly, $recurrence->frequency);
        $this->assertTrue($recurrence->is_active);
    }

    public function test_next_date_advances_by_frequency_with_monthly_clamp(): void
    {
        $service = app(RecurrenceService::class);

        $this->assertSame(
            '2026-01-22',
            $service->nextDate(Carbon::parse('2026-01-15'), RecurrenceFrequencyEnum::Weekly, 1)->toDateString(),
        );
        $this->assertSame(
            '2026-03-15',
            $service->nextDate(Carbon::parse('2026-01-15'), RecurrenceFrequencyEnum::Monthly, 2)->toDateString(),
        );
        $this->assertSame(
            '2026-02-28',
            $service->nextDate(Carbon::parse('2026-01-31'), RecurrenceFrequencyEnum::Monthly, 1)->toDateString(),
        );
        $this->assertSame(
            '2027-01-15',
            $service->nextDate(Carbon::parse('2026-01-15'), RecurrenceFrequencyEnum::Yearly, 1)->toDateString(),
        );
    }

    public function test_make_occurrence_clones_template_and_sets_anchor_dates(): void
    {
        $user = $this->makeUser();
        $status = $this->makeStatus($user);

        $template = Task::create([
            'title' => 'Pay rent',
            'status_id' => $status->id,
            'company_id' => $this->company->id,
            'is_recurrence_template' => true,
            'priority' => 'high',
            'is_continuous' => true,
        ]);

        $recurrence = TaskRecurrence::create([
            'company_id' => $this->company->id,
            'task_id' => $template->id,
            'frequency' => RecurrenceFrequencyEnum::Monthly,
            'anchor_field' => RecurrenceAnchorFieldEnum::Both,
            'offset_days' => 3,
            'generated_count' => 1,
        ]);

        $service = app(RecurrenceService::class);
        $occurrence = $service->makeOccurrence($recurrence, Carbon::parse('2026-02-10'));

        $this->assertFalse($occurrence->is_recurrence_template);
        $this->assertSame($recurrence->id, $occurrence->recurrence_id);
        $this->assertSame($this->company->id, $occurrence->company_id);
        $this->assertSame('Pay rent', $occurrence->title);
        $this->assertSame('high', $occurrence->priority);
        $this->assertSame('2026-02-10', $occurrence->due_date->toDateString());
        $this->assertSame('2026-02-07', $occurrence->planned_end->toDateString());
        $this->assertNull($occurrence->actual_start);
        $this->assertFalse($occurrence->is_continuous);
    }

    public function test_enable_for_task_creates_template_and_links_first_occurrence(): void
    {
        $user = $this->makeUser();
        $status = $this->makeStatus($user);

        $task = Task::create([
            'title' => 'Monthly report',
            'status_id' => $status->id,
            'company_id' => $this->company->id,
            'due_date' => '2026-01-31',
        ]);

        $service = app(RecurrenceService::class);
        $recurrence = $service->enableForTask($task, [
            'frequency' => RecurrenceFrequencyEnum::Monthly,
            'interval' => 1,
            'anchor_field' => RecurrenceAnchorFieldEnum::DueDate,
            'generation_mode' => RecurrenceGenerationModeEnum::ByDate,
            'stop_type' => RecurrenceStopTypeEnum::Count,
            'max_count' => 3,
        ]);

        $template = Task::withoutGlobalScopes()->findOrFail($recurrence->task_id);
        $this->assertTrue($template->is_recurrence_template);
        $this->assertNotSame($task->id, $template->id);
        $this->assertSame('Monthly report', $template->title);

        $this->assertSame($recurrence->id, $task->fresh()->recurrence_id);
        $this->assertSame(1, $recurrence->generated_count);
        $this->assertTrue($recurrence->is_active);
        $this->assertSame('2026-02-28', $recurrence->next_run_date->toDateString());
    }

    public function test_stop_series_deactivates_recurrence(): void
    {
        $user = $this->makeUser();
        $status = $this->makeStatus($user);
        $task = Task::create([
            'title' => 'X',
            'status_id' => $status->id,
            'company_id' => $this->company->id,
            'due_date' => '2026-01-10',
        ]);

        $service = app(RecurrenceService::class);
        $recurrence = $service->enableForTask($task, [
            'frequency' => RecurrenceFrequencyEnum::Weekly,
            'interval' => 1,
            'anchor_field' => RecurrenceAnchorFieldEnum::DueDate,
            'generation_mode' => RecurrenceGenerationModeEnum::ByDate,
            'stop_type' => RecurrenceStopTypeEnum::Never,
        ]);

        $service->stopSeries($recurrence);

        $this->assertFalse($recurrence->fresh()->is_active);
    }

    public function test_closing_an_on_completion_occurrence_generates_next(): void
    {
        $user = $this->makeUser();
        $todo = $this->makeStatus($user);
        $closed = Status::create([
            'name' => 'Done',
            'phase' => 'closed',
            'color' => '#000000',
            'sort_order' => 2,
            'active' => true,
            'company_id' => $this->company->id,
        ]);

        $task = Task::create([
            'title' => 'Recurring chore',
            'status_id' => $todo->id,
            'company_id' => $this->company->id,
            'due_date' => '2026-01-10',
        ]);

        $service = app(RecurrenceService::class);
        $recurrence = $service->enableForTask($task, [
            'frequency' => RecurrenceFrequencyEnum::Weekly,
            'interval' => 1,
            'anchor_field' => RecurrenceAnchorFieldEnum::DueDate,
            'generation_mode' => RecurrenceGenerationModeEnum::OnCompletion,
            'stop_type' => RecurrenceStopTypeEnum::Never,
        ]);

        $task->refresh();
        $task->update(['status_id' => $closed->id]);

        $this->assertGreaterThanOrEqual(2, $recurrence->occurrences()->count());

        $countAfterFirstClose = $recurrence->occurrences()->count();
        $task->update(['title' => 'touch']);
        $this->assertSame($countAfterFirstClose, $recurrence->occurrences()->count());
    }
}
