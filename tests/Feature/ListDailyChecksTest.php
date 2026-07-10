<?php

namespace Tests\Feature;

use App\Filament\App\Resources\TaskResource\Pages\ViewTask;
use App\Filament\App\Widgets\ListDailyChecks;
use App\Models\Company;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class ListDailyChecksTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    private ?Status $status = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
    }

    private function makeStatus(): Status
    {
        return $this->status ??= Status::create([
            'name' => 'Todo',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->company->id,
        ]);
    }

    private function makeTask(string $title, bool $continuous): Task
    {
        return Task::create([
            'title' => $title,
            'status_id' => $this->makeStatus()->id,
            'company_id' => $this->company->id,
            'priority' => 'medium',
            'is_continuous' => $continuous,
        ]);
    }

    public function test_widget_shows_only_continuous_tasks(): void
    {
        $continuous = $this->makeTask('Habit', true);
        $plain = $this->makeTask('Plain', false);

        Livewire::test(ListDailyChecks::class)
            ->assertCanSeeTableRecords([$continuous])
            ->assertCanNotSeeTableRecords([$plain]);
    }

    public function test_widget_has_done_today_and_streak_columns(): void
    {
        $this->makeTask('Habit', true);

        Livewire::test(ListDailyChecks::class)
            ->assertTableColumnExists('done_today')
            ->assertTableColumnExists('streak');
    }

    public function test_clicking_today_column_toggles_check_in(): void
    {
        $task = $this->makeTask('Habit', true);

        Livewire::test(ListDailyChecks::class)
            ->callTableColumnAction('done_today', $task);

        $this->assertTrue($task->refresh()->isDoneToday());

        Livewire::test(ListDailyChecks::class)
            ->callTableColumnAction('done_today', $task);

        $this->assertFalse($task->refresh()->isDoneToday());
    }

    public function test_widget_hidden_when_no_continuous_tasks(): void
    {
        $this->makeTask('Plain', false);

        $this->assertFalse(ListDailyChecks::canView());
    }

    public function test_widget_visible_when_continuous_task_exists(): void
    {
        $this->makeTask('Habit', true);

        $this->assertTrue(ListDailyChecks::canView());
    }

    public function test_done_today_column_renders_a_visible_icon(): void
    {
        $this->makeTask('Habit', true);

        Livewire::test(ListDailyChecks::class)
            ->assertSeeHtml('fi-ta-icon-item');
    }

    public function test_clicking_a_row_links_to_the_task_view_page(): void
    {
        $task = $this->makeTask('Habit', true);

        $url = Livewire::test(ListDailyChecks::class)
            ->instance()
            ->getTable()
            ->getRecordUrl($task);

        $this->assertSame(
            ViewTask::getUrl([$task->id]),
            $url,
        );
    }

    public function test_widget_lists_pending_checks_before_done_ones(): void
    {
        $done = $this->makeTask('Done habit', true);
        $pending = $this->makeTask('Pending habit', true);
        $done->markDoneToday();

        Livewire::test(ListDailyChecks::class)
            ->assertCanSeeTableRecords([$pending, $done], inOrder: true);
    }
}
