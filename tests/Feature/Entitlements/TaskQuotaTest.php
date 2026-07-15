<?php

namespace Tests\Feature\Entitlements;

use App\Contracts\FeatureGate;
use App\Enums\FeatureEnum;
use App\Enums\QuotaEnum;
use App\Filament\App\Resources\TaskResource;
use App\Filament\App\Resources\TaskResource\Pages\CreateTask;
use App\Filament\App\Resources\TaskResource\Pages\ListTasks;
use App\Models\Company;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class TaskQuotaTest extends TestCase
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

    private function makeStatus(): Status
    {
        return Status::create([
            'name' => 'Todo',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->company->id,
        ]);
    }

    private function makeTask(array $attrs = []): Task
    {
        return Task::create(array_merge([
            'title' => 'T',
            'status_id' => $this->makeStatus()->id,
            'priority' => 'medium',
        ], $attrs));
    }

    private function bindLimit(?int $limit): void
    {
        $this->app->bind(FeatureGate::class, fn () => new class($limit) implements FeatureGate
        {
            public function __construct(private ?int $limit) {}

            public function allows(Company $company, FeatureEnum $feature): bool
            {
                return true;
            }

            public function limitFor(Company $company, QuotaEnum $quota): ?int
            {
                return $quota === QuotaEnum::TasksPerMonth ? $this->limit : null;
            }
        });
    }

    public function test_count_ignores_tasks_from_previous_month(): void
    {
        $this->actInCompany();

        $old = $this->makeTask();
        $old->forceFill(['created_at' => now()->subMonthNoOverflow()])->save();
        $this->makeTask(); // this month

        $this->assertSame(1, Task::createdThisMonthCount());
    }

    public function test_create_is_blocked_when_over_the_monthly_limit(): void
    {
        $this->actInCompany();
        $this->bindLimit(2);
        $this->makeTask();
        $this->makeTask(); // now at limit = 2

        $status = $this->makeStatus();

        Livewire::test(CreateTask::class)
            ->fillForm([
                'title' => 'Over limit',
                'status_id' => $status->id,
                'priority' => 'medium',
            ])
            ->call('create')
            ->assertNotified();

        $this->assertSame(2, Task::count()); // did not create the 3rd
    }

    public function test_create_passes_when_under_limit(): void
    {
        $this->actInCompany();
        $this->bindLimit(5);

        $status = $this->makeStatus();

        Livewire::test(CreateTask::class)
            ->fillForm([
                'title' => 'Under limit',
                'status_id' => $status->id,
                'priority' => 'medium',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Task::count());
    }

    public function test_clone_is_blocked_when_over_the_monthly_limit(): void
    {
        $this->actInCompany();
        $this->bindLimit(1);
        $task = $this->makeTask(); // 1 task now, already at the limit

        TaskResource::clone($task);

        $this->assertSame(1, Task::count());
        $this->assertNotEmpty(session('filament.notifications'));
    }

    public function test_clone_collection_is_blocked_when_over_the_monthly_limit(): void
    {
        $this->actInCompany();
        $this->bindLimit(1);
        $task = $this->makeTask(); // 1 task now, already at the limit

        TaskResource::cloneCollection(collect([$task]));

        $this->assertSame(1, Task::count());
        $this->assertNotEmpty(session('filament.notifications'));
    }

    public function test_clone_row_action_hidden_when_over_the_monthly_limit(): void
    {
        $this->actInCompany();
        $this->bindLimit(1);
        $task = $this->makeTask(); // 1 task now, already at the limit

        Livewire::test(ListTasks::class)->assertTableActionHidden('Clone', $task);

        $this->bindLimit(5);
        Livewire::test(ListTasks::class)->assertTableActionVisible('Clone', $task);
    }
}
