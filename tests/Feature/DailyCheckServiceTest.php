<?php

namespace Tests\Feature;

use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\DailyCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class DailyCheckServiceTest extends TestCase
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
            'name' => 'Todo',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->user->company->id,
        ]);
    }

    private function makeTask(string $title, bool $continuous): Task
    {
        return Task::create([
            'title' => $title,
            'status_id' => $this->makeStatus()->id,
            'company_id' => $this->user->company->id,
            'priority' => 'medium',
            'is_continuous' => $continuous,
        ]);
    }

    public function test_query_returns_only_continuous_tasks(): void
    {
        $continuous = $this->makeTask('Habit', true);
        $this->makeTask('Plain', false);

        $ids = DailyCheckService::query()->pluck('id')->all();

        $this->assertSame([$continuous->id], $ids);
    }

    public function test_toggle_marks_done_then_undone(): void
    {
        $task = $this->makeTask('Habit', true);

        DailyCheckService::toggle($task);
        $this->assertTrue($task->refresh()->isDoneToday());

        DailyCheckService::toggle($task);
        $this->assertFalse($task->refresh()->isDoneToday());
    }

    public function test_toggle_does_nothing_for_non_continuous_task(): void
    {
        $task = $this->makeTask('Plain', false);

        DailyCheckService::toggle($task);

        $this->assertFalse($task->refresh()->isDoneToday());
        $this->assertSame(0, $task->taskCompletions()->count());
    }

    public function test_record_classes_reflect_state(): void
    {
        $task = $this->makeTask('Habit', true);
        $plain = $this->makeTask('Plain', false);

        $this->assertSame('fi-daily-check-pending', DailyCheckService::recordClasses($task));
        $this->assertNull(DailyCheckService::recordClasses($plain));

        $task->markDoneToday();
        $this->assertSame('fi-daily-check-done', DailyCheckService::recordClasses($task->refresh()));
    }
}
