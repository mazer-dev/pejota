<?php

namespace Tests\Feature;

use App\Enums\ContinuousModeEnum;
use App\Filament\App\Resources\TaskResource\Pages\CreateTask;
use App\Filament\App\Resources\TaskResource\Pages\ListTasks;
use App\Filament\App\Resources\TaskResource\Pages\ViewTask;
use App\Helpers\PejotaHelper;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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

    public function test_view_page_shows_streak_and_completion_dates_for_daily_check(): void
    {
        $status = $this->makeStatus();
        $task = Task::create([
            'title' => 'Daily habit',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'priority' => 'medium',
            'is_continuous' => true,
        ]);
        $task->markDoneToday();

        $today = Carbon::today(PejotaHelper::getUserTimeZone())->format(PejotaHelper::getUserDateFormat());

        Livewire::test(ViewTask::class, ['record' => $task->id])
            ->assertSee(__('Daily checks'))
            ->assertSee($today);
    }

    public function test_table_has_streak_column(): void
    {
        Livewire::test(ListTasks::class)
            ->assertTableColumnExists('streak');
    }

    public function test_table_has_done_today_column(): void
    {
        Livewire::test(ListTasks::class)
            ->assertTableColumnExists('done_today');
    }

    public function test_done_daily_check_is_hidden_from_opened_tab_until_next_day(): void
    {
        $status = $this->makeStatus();
        $task = Task::create([
            'title' => 'Daily habit',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'priority' => 'medium',
            'is_continuous' => true,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-16 10:00:00'));
        $task->markDoneToday();

        Livewire::test(ListTasks::class)
            ->assertCanNotSeeTableRecords([$task]);

        Carbon::setTestNow(Carbon::parse('2026-06-17 10:00:00'));

        Livewire::test(ListTasks::class)
            ->assertCanSeeTableRecords([$task]);
    }

    public function test_pending_daily_check_still_visible_in_opened_tab(): void
    {
        $status = $this->makeStatus();
        $task = Task::create([
            'title' => 'Daily habit',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'priority' => 'medium',
            'is_continuous' => true,
        ]);

        Livewire::test(ListTasks::class)
            ->assertCanSeeTableRecords([$task]);
    }

    public function test_hide_continuous_filter_does_not_affect_daily_checks_tab(): void
    {
        $status = $this->makeStatus();
        $continuous = Task::create([
            'title' => 'Continuous',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'priority' => 'medium',
            'is_continuous' => true,
        ]);

        Livewire::test(ListTasks::class)
            ->set('activeTab', 'daily_checks')
            ->filterTable('hide_continuous')
            ->assertCanSeeTableRecords([$continuous]);
    }

    public function test_clicking_today_column_marks_check_in(): void
    {
        $status = $this->makeStatus();
        $task = Task::create([
            'title' => 'Daily habit',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'priority' => 'medium',
            'is_continuous' => true,
        ]);

        Livewire::test(ListTasks::class)
            ->callTableColumnAction('done_today', $task);

        $this->assertTrue($task->refresh()->isDoneToday());
    }

    public function test_clicking_today_column_again_removes_check_in(): void
    {
        $status = $this->makeStatus();
        $task = Task::create([
            'title' => 'Daily habit',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'priority' => 'medium',
            'is_continuous' => true,
        ]);
        $task->markDoneToday();

        Livewire::test(ListTasks::class)
            ->set('activeTab', 'daily_checks')
            ->callTableColumnAction('done_today', $task);

        $this->assertFalse($task->refresh()->isDoneToday());
    }

    public function test_daily_check_rows_carry_pending_and_done_state_classes(): void
    {
        $status = $this->makeStatus();
        $pending = Task::create([
            'title' => 'Pending habit',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'priority' => 'medium',
            'is_continuous' => true,
        ]);
        $done = Task::create([
            'title' => 'Done habit',
            'status_id' => $status->id,
            'company_id' => $this->user->company->id,
            'priority' => 'medium',
            'is_continuous' => true,
        ]);
        $done->markDoneToday();

        Livewire::test(ListTasks::class)
            ->assertSeeHtml('fi-daily-check-pending');

        Livewire::test(ListTasks::class)
            ->set('activeTab', 'daily_checks')
            ->assertSeeHtml('fi-daily-check-done');
    }
}
