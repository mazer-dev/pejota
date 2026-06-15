<?php

namespace Tests\Feature;

use App\Enums\ContinuousModeEnum;
use App\Models\Scopes\ExcludeRecurrenceTemplatesScope;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskContinuousTest extends TestCase
{
    use RefreshDatabase;

    public function test_continuous_mode_enum_has_simple_and_daily_check(): void
    {
        $this->assertSame('simple', ContinuousModeEnum::Simple->value);
        $this->assertSame('daily_check', ContinuousModeEnum::DailyCheck->value);
        $this->assertSame('Simple', ContinuousModeEnum::Simple->getLabel());
        $this->assertSame('Daily check', ContinuousModeEnum::DailyCheck->getLabel());
    }

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function makeStatus(User $user, string $phase = 'todo'): Status
    {
        return Status::create([
            'name' => 'Status '.$phase,
            'phase' => $phase,
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $user->company->id,
        ]);
    }

    private function makeTask(User $user, Status $status, array $attributes = []): Task
    {
        return Task::create(array_merge([
            'title' => 'Task',
            'status_id' => $status->id,
            'company_id' => $user->company->id,
        ], $attributes));
    }

    public function test_global_scope_hides_recurrence_templates(): void
    {
        $user = $this->makeUser();
        $status = $this->makeStatus($user);

        $normal = $this->makeTask($user, $status, ['title' => 'Normal']);
        $template = $this->makeTask($user, $status, ['title' => 'Template', 'is_recurrence_template' => true]);

        $ids = Task::query()->pluck('id');

        $this->assertTrue($ids->contains($normal->id));
        $this->assertFalse($ids->contains($template->id));
    }

    public function test_templates_are_reachable_without_global_scope(): void
    {
        $user = $this->makeUser();
        $status = $this->makeStatus($user);
        $template = $this->makeTask($user, $status, ['is_recurrence_template' => true]);

        $found = Task::withoutGlobalScope(ExcludeRecurrenceTemplatesScope::class)
            ->find($template->id);

        $this->assertNotNull($found);
    }

    public function test_mark_done_today_creates_one_completion_and_is_idempotent(): void
    {
        $user = $this->makeUser();
        $status = $this->makeStatus($user);
        $task = $this->makeTask($user, $status, [
            'is_continuous' => true,
            'continuous_mode' => ContinuousModeEnum::DailyCheck,
        ]);

        $task->markDoneToday();
        $task->markDoneToday();

        $this->assertSame(1, $task->taskCompletions()->count());
        $this->assertTrue($task->isDoneToday());
    }

    public function test_current_streak_counts_consecutive_days(): void
    {
        $user = $this->makeUser();
        $status = $this->makeStatus($user);
        $task = $this->makeTask($user, $status, [
            'is_continuous' => true,
            'continuous_mode' => ContinuousModeEnum::DailyCheck,
        ]);

        $today = Carbon::today();
        foreach ([0, 1, 2] as $daysAgo) {
            $task->taskCompletions()->create([
                'company_id' => $user->company->id,
                'user_id' => $user->id,
                'completed_on' => $today->copy()->subDays($daysAgo)->toDateString(),
            ]);
        }
        $task->taskCompletions()->create([
            'company_id' => $user->company->id,
            'user_id' => $user->id,
            'completed_on' => $today->copy()->subDays(4)->toDateString(),
        ]);

        $this->assertSame(3, $task->currentStreak());
    }

    public function test_not_done_when_only_yesterday_completed(): void
    {
        $user = $this->makeUser();
        $status = $this->makeStatus($user);
        $task = $this->makeTask($user, $status, [
            'is_continuous' => true,
            'continuous_mode' => ContinuousModeEnum::DailyCheck,
        ]);

        $task->taskCompletions()->create([
            'company_id' => $user->company->id,
            'user_id' => $user->id,
            'completed_on' => Carbon::today()->subDay()->toDateString(),
        ]);

        $this->assertFalse($task->isDoneToday());
        $this->assertSame(0, $task->currentStreak());
    }

    public function test_clearing_continuous_nulls_the_mode(): void
    {
        $user = $this->makeUser();
        $status = $this->makeStatus($user);
        $task = $this->makeTask($user, $status, [
            'is_continuous' => true,
            'continuous_mode' => ContinuousModeEnum::DailyCheck,
        ]);

        $task->update(['is_continuous' => false]);

        $this->assertNull($task->fresh()->continuous_mode);
    }

    public function test_ordered_for_list_puts_continuous_first_and_done_today_last(): void
    {
        $user = $this->makeUser();
        $status = $this->makeStatus($user);

        // DailyDone is created FIRST among the continuous tasks (lowest id) and
        // marked done today. A plain insertion-order sort would place it before
        // the still-pending continuous tasks; the done-today rule must sink it
        // below them. This isolates the done-today ordering from id ordering.
        $dailyDone = $this->makeTask($user, $status, [
            'title' => 'DailyDone',
            'is_continuous' => true,
            'continuous_mode' => ContinuousModeEnum::DailyCheck,
        ]);
        $dailyPending = $this->makeTask($user, $status, [
            'title' => 'DailyPending',
            'is_continuous' => true,
            'continuous_mode' => ContinuousModeEnum::DailyCheck,
        ]);
        $continuousSimple = $this->makeTask($user, $status, [
            'title' => 'ContinuousSimple',
            'is_continuous' => true,
            'continuous_mode' => ContinuousModeEnum::Simple,
        ]);
        $plain = $this->makeTask($user, $status, ['title' => 'Plain']);
        $dailyDone->markDoneToday();

        $titles = Task::query()->orderedForList()->pluck('title')->all();

        // Non-continuous task is last.
        $this->assertSame('Plain', $titles[count($titles) - 1]);

        $doneIndex = array_search('DailyDone', $titles, true);
        $pendingIndex = array_search('DailyPending', $titles, true);
        $simpleIndex = array_search('ContinuousSimple', $titles, true);
        $plainIndex = array_search('Plain', $titles, true);

        // Both pending continuous tasks come before the done-today daily,
        // even though the done daily has a lower id.
        $this->assertLessThan($doneIndex, $pendingIndex);
        $this->assertLessThan($doneIndex, $simpleIndex);

        // The done-today daily is still a continuous task, so it stays above
        // the plain task.
        $this->assertLessThan($plainIndex, $doneIndex);
    }
}
