<?php

namespace Tests\Feature;

use App\Filament\App\Resources\WorkSessionResource\Pages\CreateWorkSession;
use App\Filament\App\Resources\WorkSessionResource\Pages\ViewWorkSession;
use App\Helpers\PejotaHelper;
use App\Livewire\WorkSessionsTopNav;
use App\Models\Client;
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

    /**
     * The datetime-local input only accepts a plain "Y-m-d H:i"-shaped string.
     * A raw Carbon reaching $data is silently rejected by the browser, which
     * then syncs the emptied field back and wipes it on every edit.
     */
    public function test_start_is_prefilled_as_a_string_the_datetime_input_accepts(): void
    {
        $fromTask = Livewire::withQueryParams(['task' => $this->task->id])
            ->test(CreateWorkSession::class)
            ->get('data');

        $this->assertIsString($fromTask['start']);
        $this->assertSame(now()->format('Y-m-d H:i'), $fromTask['start']);
    }

    public function test_start_prefill_has_the_same_shape_as_the_plain_create_page(): void
    {
        $plain = Livewire::test(CreateWorkSession::class)->get('data');

        $fromTask = Livewire::withQueryParams(['task' => $this->task->id])
            ->test(CreateWorkSession::class)
            ->get('data');

        $this->assertSame(get_debug_type($plain['start']), get_debug_type($fromTask['start']));
    }

    public function test_billing_fields_resolve_from_the_task_like_the_top_nav_does(): void
    {
        $client = Client::create([
            'name' => 'Rated Client',
            'company_id' => $this->company->id,
            'currency' => 'BRL',
            'default_hourly_rate' => 75.00,
            'billable_default' => false,
        ]);

        $this->task->update([
            'client_id' => $client->id,
            'hourly_rate' => 120.00,
        ]);

        Livewire::withQueryParams(['task' => $this->task->id])
            ->test(CreateWorkSession::class)
            ->assertSet('data.rate', 120.00)
            ->assertSet('data.currency', 'BRL')
            ->assertSet('data.billable', false);
    }

    public function test_prefilled_billing_matches_a_session_started_from_the_top_nav(): void
    {
        $client = Client::create([
            'name' => 'Rated Client',
            'company_id' => $this->company->id,
            'currency' => 'BRL',
            'default_hourly_rate' => 75.00,
            'billable_default' => false,
        ]);

        $this->task->update([
            'client_id' => $client->id,
            'hourly_rate' => 120.00,
        ]);

        Livewire::test(WorkSessionsTopNav::class)
            ->set('newTitle', 'Started from top nav')
            ->set('newClient', $client->id)
            ->set('newTask', $this->task->id)
            ->call('startSession');

        $started = WorkSession::where('title', 'Started from top nav')->firstOrFail();

        $prefill = CreateWorkSession::getFillFormArray($this->task->fresh());

        $this->assertEquals(120.00, $started->rate, 'guards the comparison below from passing on empty values');

        $this->assertEquals($started->rate, $prefill['rate']);
        $this->assertSame($started->currency, $prefill['currency']);
        $this->assertSame((bool) $started->billable, $prefill['billable']);
    }

    public function test_creating_redirects_to_the_new_session_view_even_when_started_from_a_task(): void
    {
        Livewire::withQueryParams(['task' => $this->task->id])
            ->test(CreateWorkSession::class)
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(ViewWorkSession::getUrl([
                'record' => WorkSession::query()->firstOrFail()->id,
            ]));
    }

    public function test_creating_another_stays_on_the_create_page(): void
    {
        Livewire::withQueryParams(['task' => $this->task->id])
            ->test(CreateWorkSession::class)
            ->call('create', another: true)
            ->assertHasNoFormErrors()
            ->assertNoRedirect();
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
