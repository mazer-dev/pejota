<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Helpers\PejotaHelper;
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
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class WorkSessionCalculationTest extends TestCase
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

    private function companyId(): int
    {
        return $this->company->id;
    }

    public function test_duration_and_value_are_derived_from_start_end_and_rate(): void
    {
        $session = WorkSession::create([
            'title' => 'Dev',
            'company_id' => $this->companyId(),
            'start' => '2026-06-17 09:00:00',
            'end' => '2026-06-17 10:30:00',
            'is_running' => false,
            'rate' => 100.00,
        ]);

        $this->assertSame(90, $session->duration);
        $this->assertEquals(150.00, $session->value);
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WorkSession::create([
            'title' => 'Bad',
            'company_id' => $this->companyId(),
            'start' => '2026-06-17 10:00:00',
            'end' => '2026-06-17 09:00:00',
            'is_running' => false,
            'rate' => 100.00,
        ]);
    }

    public function test_rate_cascade_prefers_task_then_project_then_client(): void
    {
        $client = Client::create([
            'name' => 'Acme',
            'company_id' => $this->companyId(),
            'currency' => 'BRL',
            'default_hourly_rate' => 50.00,
            'billable_default' => true,
        ]);
        $project = Project::create([
            'name' => 'P1',
            'company_id' => $this->companyId(),
            'client_id' => $client->id,
            'hourly_rate' => 80.00,
        ]);
        $status = Status::create([
            'name' => 'To Do', 'phase' => 'todo', 'color' => '#000000',
            'sort_order' => 1, 'active' => true, 'company_id' => $this->companyId(),
        ]);
        $task = Task::create([
            'title' => 'T1',
            'company_id' => $this->companyId(),
            'client_id' => $client->id,
            'project_id' => $project->id,
            'status_id' => $status->id,
            'hourly_rate' => 120.00,
        ]);

        $taskSession = new WorkSession(['client_id' => $client->id, 'project_id' => $project->id, 'task_id' => $task->id]);
        $this->assertEquals(120.00, $taskSession->resolveRate());

        $projectSession = new WorkSession(['client_id' => $client->id, 'project_id' => $project->id]);
        $this->assertEquals(80.00, $projectSession->resolveRate());

        $clientSession = new WorkSession(['client_id' => $client->id]);
        $this->assertEquals(50.00, $clientSession->resolveRate());

        $this->assertEquals('BRL', $clientSession->resolveCurrency());
        $this->assertTrue($clientSession->resolveBillable());
    }

    public function test_currency_falls_back_to_base_when_no_client(): void
    {
        $session = new WorkSession;
        $this->assertSame(PejotaHelper::getUserCurrency(), $session->resolveCurrency());
    }

    public function test_billable_open_scope_excludes_invoiced_and_non_billable(): void
    {
        $base = ['company_id' => $this->companyId(), 'start' => '2026-06-17 09:00:00', 'end' => '2026-06-17 10:00:00', 'is_running' => false, 'rate' => 10.00];

        WorkSession::create($base + ['title' => 'open', 'billable' => true]);
        WorkSession::create($base + ['title' => 'not-billable', 'billable' => false]);

        $invoiceClient = Client::create([
            'name' => 'InvClient',
            'company_id' => $this->companyId(),
        ]);
        $unit = Unit::create([
            'name' => 'Hour', 'symbol' => 'h', 'company_id' => $this->companyId(),
        ]);
        $product = Product::create([
            'name' => 'Service', 'service' => true, 'digital' => false,
            'price' => 10.00, 'unit_id' => $unit->id, 'company_id' => $this->companyId(),
        ]);
        $invoice = Invoice::create([
            'number' => 'INV-1',
            'title' => 'Inv',
            'status' => InvoiceStatusEnum::DRAFT,
            'company_id' => $this->companyId(),
            'client_id' => $invoiceClient->id,
            'total' => 10.00,
        ]);
        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'name' => 'line',
            'unit_id' => $unit->id,
            'quantity' => 1,
            'price' => 10.00,
            'discount' => 0,
            'total' => 10.00,
        ]);
        WorkSession::create($base + ['title' => 'invoiced', 'billable' => true, 'invoice_item_id' => $item->id]);

        $results = WorkSession::billableOpen()->pluck('title');
        $this->assertTrue($results->contains('open'));
        $this->assertFalse($results->contains('not-billable'));
        $this->assertFalse($results->contains('invoiced'));
    }

    public function test_creating_sets_user_id_but_editing_does_not_reassign(): void
    {
        $other = User::factory()->create();

        $session = WorkSession::create([
            'title' => 'Owned',
            'company_id' => $this->companyId(),
            'user_id' => $other->id,
            'start' => '2026-06-17 09:00:00',
            'end' => '2026-06-17 10:00:00',
            'is_running' => false,
            'rate' => 0,
        ]);

        $this->assertSame($other->id, $session->user_id);

        $session->update(['title' => 'Edited by current user']);
        $this->assertSame($other->id, $session->fresh()->user_id);
    }
}
