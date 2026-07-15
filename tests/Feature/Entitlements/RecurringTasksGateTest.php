<?php

namespace Tests\Feature\Entitlements;

use App\Contracts\FeatureGate;
use App\Enums\FeatureEnum;
use App\Enums\QuotaEnum;
use App\Enums\RecurrenceAnchorFieldEnum;
use App\Enums\RecurrenceFrequencyEnum;
use App\Enums\RecurrenceGenerationModeEnum;
use App\Enums\RecurrenceStopTypeEnum;
use App\Filament\App\Resources\TaskResource;
use App\Filament\App\Resources\TaskResource\Pages\ListTasks;
use App\Models\Company;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class RecurringTasksGateTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private Company $company;

    private function actInCompany(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'email' => 'acme@example.test', 'user_id' => $user->id]);
        $user->companies()->attach($company->id, ['joined_at' => now()]);
        $this->actingInCompany($user, $company);

        $this->company = $company;
    }

    private function makeTask(): Task
    {
        $status = Status::create([
            'name' => 'Todo',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->company->id,
        ]);

        return Task::create([
            'title' => 'T',
            'status_id' => $status->id,
            'company_id' => $this->company->id,
        ]);
    }

    private function bindRecurring(bool $allow): void
    {
        $this->app->bind(FeatureGate::class, fn () => new class($allow) implements FeatureGate
        {
            public function __construct(private bool $allow) {}

            public function allows(Company $company, FeatureEnum $feature): bool
            {
                return $feature === FeatureEnum::RecurringTasks ? $this->allow : true;
            }

            public function limitFor(Company $company, QuotaEnum $quota): ?int
            {
                return null;
            }
        });
    }

    public function test_make_recurring_action_hidden_when_feature_denied(): void
    {
        $this->actInCompany();
        $task = $this->makeTask();

        $this->bindRecurring(false);
        Livewire::test(ListTasks::class)->assertTableActionHidden('makeRecurring', $task);

        $this->bindRecurring(true);
        Livewire::test(ListTasks::class)->assertTableActionVisible('makeRecurring', $task);
    }

    /**
     * `callTableAction('makeRecurring', ...)`'s test helper refuses to call a hidden action (it
     * asserts visibility first). Dropping to the lower-level `mountTableAction`/
     * `callMountedTableAction` doesn't forge past it either: in the installed Filament version
     * (v3.3.54), `StaticAction::isDisabled()` is `evaluate($this->isDisabled) || $this->isHidden()`
     * (see `vendor/filament/actions/src/Concerns/CanBeDisabled.php:20`), and both the table-action
     * and page-action mount/call methods bail out on `isDisabled()` — so a `->visible()`-only gate
     * already can't be executed through either the table or page action surface here.
     *
     * The remaining forged-call vector is invoking the handler directly, bypassing Filament's
     * action wrapper entirely (e.g. a future refactor, a queued job, or a direct call from other
     * code reusing this handler). Since `enableRecurrenceFromForm` is private, Reflection is used
     * to call it exactly as Filament's `->action()` closure would, but without going through
     * `->visible()`/`isDisabled()` at all — proving the guard lives in the handler itself, not
     * only in the UI-level condition.
     */
    public function test_make_recurring_handler_refuses_forged_execution_when_feature_denied(): void
    {
        $this->actInCompany();
        $task = $this->makeTask();

        $this->bindRecurring(false);

        $handler = new ReflectionMethod(TaskResource::class, 'enableRecurrenceFromForm');
        $handler->setAccessible(true);
        $handler->invoke(null, $task, [
            'frequency' => RecurrenceFrequencyEnum::Monthly->value,
            'interval' => 1,
            'anchor_field' => RecurrenceAnchorFieldEnum::DueDate->value,
            'generation_mode' => RecurrenceGenerationModeEnum::ByDate->value,
            'stop_type' => RecurrenceStopTypeEnum::Never->value,
        ]);

        $task->refresh();
        $this->assertNull($task->recurrence_id);
        $this->assertNotEmpty(session('filament.notifications'));
    }
}
