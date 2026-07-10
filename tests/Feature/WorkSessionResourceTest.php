<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Filament\App\Resources\WorkSessionResource;
use App\Filament\App\Resources\WorkSessionResource\Pages\CreateWorkSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class WorkSessionResourceTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
    }

    public function test_list_page_loads(): void
    {
        $this->get(WorkSessionResource::getUrl('index'))
            ->assertOk();
    }

    public function test_selecting_client_cascades_rate_and_currency_into_the_form(): void
    {
        $client = Client::create([
            'name' => 'Acme',
            'company_id' => $this->company->id,
            'currency' => 'BRL',
            'default_hourly_rate' => 90.00,
            'billable_default' => true,
        ]);

        // fillForm does not trigger afterStateUpdated hooks in Filament v3 tests,
        // so we verify the cascade via the persisted record instead of assertFormSet.
        Livewire::test(CreateWorkSession::class)
            ->fillForm([
                'title' => 'Cascade',
                'client' => $client->id,
                'start' => '2026-06-17 09:00',
                'end' => '2026-06-17 10:00',
                'duration' => 60,
                'is_running' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $session = WorkSession::where('title', 'Cascade')->first();
        $this->assertNotNull($session);
        $this->assertEquals(90.00, $session->rate);
        $this->assertSame('BRL', $session->currency);
        $this->assertEquals(90.00, $session->value); // 90/h * 1h
    }

    public function test_clone_does_not_carry_over_invoice_item_link(): void
    {
        $client = Client::create([
            'name' => 'Clone Client',
            'company_id' => $this->company->id,
        ]);
        $unit = Unit::create([
            'name' => 'Hour',
            'symbol' => 'h',
            'company_id' => $this->company->id,
        ]);
        $product = Product::create([
            'name' => 'Service',
            'service' => true,
            'digital' => false,
            'company_id' => $this->company->id,
            'unit_id' => $unit->id,
        ]);
        $invoice = Invoice::create([
            'number' => 'INV-CLONE',
            'title' => 'Inv',
            'status' => InvoiceStatusEnum::DRAFT,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'total' => 10.00,
        ]);
        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'name' => 'line',
            'quantity' => 1,
            'price' => 10.00,
            'discount' => 0,
            'total' => 10.00,
        ]);
        $source = WorkSession::create([
            'title' => 'Invoiced source',
            'company_id' => $this->company->id,
            'start' => '2026-06-17 09:00:00',
            'end' => '2026-06-17 10:00:00',
            'is_running' => false,
            'rate' => 10.00,
            'invoice_item_id' => $item->id,
        ]);

        WorkSessionResource::clone($source);

        $clone = WorkSession::where('title', 'Invoiced source')
            ->where('id', '!=', $source->id)
            ->first();

        $this->assertNotNull($clone);
        $this->assertNull($clone->invoice_item_id);
    }

    public function test_selecting_client_cascades_billable_into_the_form(): void
    {
        $client = Client::create([
            'name' => 'NonBillable Co',
            'company_id' => $this->company->id,
            'currency' => 'BRL',
            'default_hourly_rate' => 40.00,
            'billable_default' => false,
        ]);

        Livewire::test(CreateWorkSession::class)
            ->set('data.client', $client->id)
            ->assertFormSet([
                'billable' => false,
                'rate' => 40.00,
                'currency' => 'BRL',
            ]);
    }

    public function test_manual_billable_choice_is_not_overridden_for_billable_client(): void
    {
        $client = Client::create([
            'name' => 'Billable Co',
            'company_id' => $this->company->id,
            'currency' => 'BRL',
            'default_hourly_rate' => 40.00,
            'billable_default' => true,
        ]);

        Livewire::test(CreateWorkSession::class)
            ->fillForm([
                'title' => 'Pro bono hour',
                'client' => $client->id,
                'start' => '2026-06-17 09:00',
                'end' => '2026-06-17 10:00',
                'duration' => 60,
                'is_running' => false,
                'rate' => 40.00,
                'billable' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $session = WorkSession::where('title', 'Pro bono hour')->first();
        $this->assertNotNull($session);
        $this->assertFalse($session->billable, 'A deliberate billable=false must survive save even for a billable client');
    }

    public function test_selecting_project_fills_client_in_the_form(): void
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id]);
        $project = Project::create([
            'name' => 'Apollo',
            'company_id' => $this->company->id,
            'client_id' => $client->id,
        ]);

        Livewire::test(CreateWorkSession::class)
            ->set('data.project', $project->id)
            ->assertFormSet(['client' => $client->id]);
    }

    public function test_selecting_task_fills_project_and_client_in_the_form(): void
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id]);
        $project = Project::create([
            'name' => 'Apollo',
            'company_id' => $this->company->id,
            'client_id' => $client->id,
        ]);
        $status = Status::create([
            'name' => 'To Do',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->company->id,
        ]);
        $task = Task::create([
            'title' => 'Build feature',
            'status_id' => $status->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
        ]);

        Livewire::test(CreateWorkSession::class)
            ->set('data.task', $task->id)
            ->assertFormSet([
                'project' => $project->id,
                'client' => $client->id,
            ]);
    }

    public function test_selecting_client_with_single_project_fills_the_project(): void
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id]);
        $project = Project::create([
            'name' => 'Apollo',
            'company_id' => $this->company->id,
            'client_id' => $client->id,
        ]);

        Livewire::test(CreateWorkSession::class)
            ->set('data.client', $client->id)
            ->assertFormSet(['project' => $project->id]);
    }

    public function test_selecting_client_with_multiple_projects_does_not_fill_the_project(): void
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id]);
        Project::create([
            'name' => 'Apollo',
            'company_id' => $this->company->id,
            'client_id' => $client->id,
        ]);
        Project::create([
            'name' => 'Gemini',
            'company_id' => $this->company->id,
            'client_id' => $client->id,
        ]);

        Livewire::test(CreateWorkSession::class)
            ->set('data.client', $client->id)
            ->assertFormSet(['project' => null]);
    }

    public function test_selecting_project_with_single_task_fills_the_task(): void
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id]);
        $project = Project::create([
            'name' => 'Apollo',
            'company_id' => $this->company->id,
            'client_id' => $client->id,
        ]);
        $status = Status::create([
            'name' => 'To Do',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->company->id,
        ]);
        $task = Task::create([
            'title' => 'Only task',
            'status_id' => $status->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
        ]);

        Livewire::test(CreateWorkSession::class)
            ->set('data.project', $project->id)
            ->assertFormSet(['task' => $task->id]);
    }

    public function test_selecting_client_chains_down_to_single_project_and_task(): void
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id]);
        $project = Project::create([
            'name' => 'Apollo',
            'company_id' => $this->company->id,
            'client_id' => $client->id,
        ]);
        $status = Status::create([
            'name' => 'To Do',
            'phase' => 'todo',
            'color' => '#000000',
            'sort_order' => 1,
            'active' => true,
            'company_id' => $this->company->id,
        ]);
        $task = Task::create([
            'title' => 'Only task',
            'status_id' => $status->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
        ]);

        Livewire::test(CreateWorkSession::class)
            ->set('data.client', $client->id)
            ->assertFormSet([
                'project' => $project->id,
                'task' => $task->id,
            ]);
    }

    public function test_end_before_start_shows_form_error(): void
    {
        Livewire::test(CreateWorkSession::class)
            ->fillForm([
                'title' => 'Bad time',
                'start' => '2026-06-17 11:00',
                'end' => '2026-06-17 09:00',
                'is_running' => false,
                'rate' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['end']);
    }
}
