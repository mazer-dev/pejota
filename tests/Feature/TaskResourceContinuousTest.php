<?php

namespace Tests\Feature;

use App\Enums\ContinuousModeEnum;
use App\Filament\App\Resources\TaskResource\Pages\CreateTask;
use App\Filament\App\Resources\TaskResource\Pages\ListTasks;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class TaskResourceContinuousTest extends TestCase
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

    private function makeStatus(string $phase = 'todo'): Status
    {
        return Status::create([
            'name' => 'Status '.$phase,
            'phase' => $phase,
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->user->company->id,
        ]);
    }

    public function test_can_create_continuous_daily_check_task(): void
    {
        $status = $this->makeStatus();

        Livewire::test(CreateTask::class)
            ->fillForm([
                'title' => 'Review inbox',
                'status_id' => $status->id,
                'priority' => 'medium',
                'is_continuous' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::query()->where('title', 'Review inbox')->first();

        $this->assertNotNull($task);
        $this->assertTrue($task->is_continuous);
        $this->assertSame(ContinuousModeEnum::DailyCheck, $task->continuous_mode);
        $this->assertTrue($task->isDailyCheck());
    }

    public function test_mark_done_today_action_records_completion(): void
    {
        $status = $this->makeStatus();
        $task = Task::create([
            'title' => 'Daily',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'is_continuous' => true,
            'continuous_mode' => ContinuousModeEnum::DailyCheck,
        ]);

        Livewire::test(ListTasks::class)
            ->callTableAction('markDoneToday', $task);

        $this->assertTrue($task->refresh()->isDoneToday());
    }

    public function test_done_today_action_visible_for_continuous_and_hidden_for_plain(): void
    {
        $status = $this->makeStatus();
        $continuous = Task::create([
            'title' => 'Continuous',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'is_continuous' => true,
        ]);
        $plain = Task::create([
            'title' => 'Plain',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
        ]);

        Livewire::test(ListTasks::class)
            ->assertTableActionVisible('markDoneToday', $continuous)
            ->assertTableActionHidden('markDoneToday', $plain);
    }

    public function test_continuous_filter_shows_only_continuous(): void
    {
        $status = $this->makeStatus();
        $continuous = Task::create([
            'title' => 'Continuous',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'is_continuous' => true,
            'continuous_mode' => ContinuousModeEnum::Simple,
        ]);
        $plain = Task::create([
            'title' => 'Plain',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
        ]);

        Livewire::test(ListTasks::class)
            ->filterTable('is_continuous')
            ->assertCanSeeTableRecords([$continuous])
            ->assertCanNotSeeTableRecords([$plain]);
    }

    public function test_default_order_pins_continuous_tasks_to_top(): void
    {
        $status = $this->makeStatus();
        $plain = Task::create([
            'title' => 'Apple',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
        ]);
        $continuous = Task::create([
            'title' => 'Zebra',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'is_continuous' => true,
        ]);

        Livewire::test(ListTasks::class)
            ->assertCanSeeTableRecords([$continuous, $plain], inOrder: true);
    }

    public function test_sorting_by_column_does_not_pin_continuous_tasks(): void
    {
        $status = $this->makeStatus();
        $plain = Task::create([
            'title' => 'Apple',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
        ]);
        $continuous = Task::create([
            'title' => 'Zebra',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'is_continuous' => true,
        ]);

        Livewire::test(ListTasks::class)
            ->sortTable('title')
            ->assertCanSeeTableRecords([$plain, $continuous], inOrder: true);
    }

    public function test_daily_checks_tab_shows_only_continuous(): void
    {
        $status = $this->makeStatus();
        $continuous = Task::create([
            'title' => 'Continuous',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'is_continuous' => true,
        ]);
        $plain = Task::create([
            'title' => 'Plain',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
        ]);

        Livewire::test(ListTasks::class)
            ->set('activeTab', 'daily_checks')
            ->assertCanSeeTableRecords([$continuous])
            ->assertCanNotSeeTableRecords([$plain]);
    }

    public function test_hide_continuous_filter_removes_continuous_tasks(): void
    {
        $status = $this->makeStatus();
        $continuous = Task::create([
            'title' => 'Continuous',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'is_continuous' => true,
        ]);
        $plain = Task::create([
            'title' => 'Plain',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
        ]);

        Livewire::test(ListTasks::class)
            ->filterTable('hide_continuous')
            ->assertCanSeeTableRecords([$plain])
            ->assertCanNotSeeTableRecords([$continuous]);
    }
}
