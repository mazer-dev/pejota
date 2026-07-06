<?php

namespace Tests\Feature;

use App\Enums\RecurrenceAnchorFieldEnum;
use App\Enums\RecurrenceFrequencyEnum;
use App\Enums\RecurrenceGenerationModeEnum;
use App\Enums\PriorityEnum;
use App\Enums\RecurrenceStopTypeEnum;
use App\Filament\App\Resources\TaskResource\Pages\ListTasks;
use App\Filament\App\Resources\TaskResource\Pages\ViewTask;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\RecurrenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class TaskRecurrenceUiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Landlord::addTenant('company_id', $this->user->company->id);
    }

    private function makeStatus(): Status
    {
        return Status::create([
            'name' => 'To Do', 'phase' => 'todo', 'color' => '#000000',
            'sort_order' => 1, 'active' => true, 'company_id' => $this->user->company->id,
        ]);
    }

    public function test_make_recurring_action_creates_rule_and_template(): void
    {
        $status = $this->makeStatus();
        $task = Task::create([
            'title' => 'Invoice client', 'status_id' => $status->id,
            'company_id' => $this->user->company->id, 'due_date' => '2026-01-15',
        ]);

        Livewire::test(ListTasks::class)
            ->callTableAction('makeRecurring', $task, data: [
                'frequency' => RecurrenceFrequencyEnum::Monthly->value,
                'interval' => 1,
                'anchor_field' => RecurrenceAnchorFieldEnum::DueDate->value,
                'generation_mode' => RecurrenceGenerationModeEnum::ByDate->value,
                'stop_type' => RecurrenceStopTypeEnum::Never->value,
            ]);

        $task->refresh();
        $this->assertNotNull($task->recurrence_id);
        $this->assertTrue($task->recurrence->is_active);
        $this->assertSame(1, Task::query()->count());
    }

    public function test_make_recurring_action_on_view_page_creates_rule(): void
    {
        $status = $this->makeStatus();
        $task = Task::create([
            'title' => 'Invoice client', 'status_id' => $status->id,
            'company_id' => $this->user->company->id, 'due_date' => '2026-01-15',
            'priority' => PriorityEnum::MEDIUM->value,
        ]);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->callAction('makeRecurring', data: [
                'frequency' => RecurrenceFrequencyEnum::Monthly->value,
                'interval' => 1,
                'anchor_field' => RecurrenceAnchorFieldEnum::DueDate->value,
                'generation_mode' => RecurrenceGenerationModeEnum::ByDate->value,
                'stop_type' => RecurrenceStopTypeEnum::Never->value,
            ]);

        $task->refresh();
        $this->assertNotNull($task->recurrence_id);
        $this->assertTrue($task->recurrence->is_active);
    }

    public function test_stop_series_action_deactivates(): void
    {
        $status = $this->makeStatus();
        $task = Task::create([
            'title' => 'Recurring', 'status_id' => $status->id,
            'company_id' => $this->user->company->id, 'due_date' => '2026-01-15',
        ]);
        $recurrence = app(RecurrenceService::class)->enableForTask($task, [
            'frequency' => RecurrenceFrequencyEnum::Weekly,
            'interval' => 1,
            'anchor_field' => RecurrenceAnchorFieldEnum::DueDate,
            'generation_mode' => RecurrenceGenerationModeEnum::ByDate,
            'stop_type' => RecurrenceStopTypeEnum::Never,
        ]);

        Livewire::test(ListTasks::class)
            ->callTableAction('stopSeries', $task->fresh());

        $this->assertFalse($recurrence->fresh()->is_active);
    }

    public function test_recurring_filter_shows_only_recurring_occurrences(): void
    {
        $status = $this->makeStatus();
        $recurring = Task::create([
            'title' => 'Recurring', 'status_id' => $status->id,
            'company_id' => $this->user->company->id, 'due_date' => '2026-01-15',
        ]);
        app(RecurrenceService::class)->enableForTask($recurring, [
            'frequency' => RecurrenceFrequencyEnum::Weekly,
            'interval' => 1,
            'anchor_field' => RecurrenceAnchorFieldEnum::DueDate,
            'generation_mode' => RecurrenceGenerationModeEnum::ByDate,
            'stop_type' => RecurrenceStopTypeEnum::Never,
        ]);
        $plain = Task::create([
            'title' => 'Plain', 'status_id' => $status->id,
            'company_id' => $this->user->company->id,
        ]);

        Livewire::test(ListTasks::class)
            ->filterTable('recurring')
            ->assertCanSeeTableRecords([$recurring->fresh()])
            ->assertCanNotSeeTableRecords([$plain]);
    }
}
