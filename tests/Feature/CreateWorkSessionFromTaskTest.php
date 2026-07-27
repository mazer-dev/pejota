<?php

namespace Tests\Feature;

use App\Filament\App\Resources\WorkSessionResource\Pages\CreateWorkSession;
use App\Helpers\PejotaHelper;
use App\Models\Company;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class CreateWorkSessionFromTaskTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);

        $status = Status::create([
            'name' => 'To Do',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->company->id,
        ]);

        $this->task = Task::create([
            'title' => 'Deploy to production',
            'company_id' => $this->company->id,
            'status_id' => $status->id,
            'user_id' => $this->user->id,
            'priority' => 'medium',
        ]);
    }

    public function test_form_keeps_defaults_that_the_task_prefill_does_not_provide(): void
    {
        Livewire::withQueryParams(['task' => $this->task->id])
            ->test(CreateWorkSession::class)
            ->assertSet('data.currency', PejotaHelper::getUserCurrency())
            ->assertSet('data.billable', true);
    }

    public function test_session_is_created_from_a_task_without_touching_any_field(): void
    {
        Livewire::withQueryParams(['task' => $this->task->id])
            ->test(CreateWorkSession::class)
            ->call('create')
            ->assertHasNoFormErrors();

        $session = WorkSession::query()->firstOrFail();

        $this->assertSame($this->task->id, $session->task_id);
        $this->assertSame(PejotaHelper::getUserCurrency(), $session->currency);
        $this->assertTrue((bool) $session->billable);
        $this->assertTrue((bool) $session->is_running);
    }
}
