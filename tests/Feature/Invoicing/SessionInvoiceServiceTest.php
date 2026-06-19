<?php

namespace Tests\Feature\Invoicing;

use App\Enums\InvoiceStatusEnum;
use App\Enums\TimesheetGrouping;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Project;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Invoicing\SessionInvoiceRequest;
use App\Services\Invoicing\SessionInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class SessionInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Product $product;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Landlord::addTenant('company_id', $this->user->company->id);
        $this->client = Client::create(['name' => 'Acme', 'company_id' => $this->user->company->id, 'currency' => 'BRL']);
        $this->unit = Unit::create(['name' => 'Hour', 'symbol' => 'h', 'company_id' => $this->user->company->id]);
        $this->product = Product::create(['name' => 'Consulting', 'symbol' => 'C', 'service' => true, 'digital' => false, 'company_id' => $this->user->company->id, 'unit_id' => $this->unit->id, 'price' => 100, 'cost' => 0]);
    }

    private function makeSession(array $attrs): WorkSession
    {
        return WorkSession::create(array_merge([
            'title' => 'Work', 'company_id' => $this->user->company->id, 'client_id' => $this->client->id,
            'is_running' => false, 'rate' => 100.00, 'billable' => true,
        ], $attrs));
    }

    private function request(array $overrides = []): SessionInvoiceRequest
    {
        return new SessionInvoiceRequest(
            clientId: $overrides['clientId'] ?? $this->client->id,
            from: $overrides['from'] ?? CarbonImmutable::parse('2026-06-01 00:00:00'),
            to: $overrides['to'] ?? CarbonImmutable::parse('2026-06-30 23:59:59'),
            timezone: $overrides['timezone'] ?? 'UTC',
            grouping: $overrides['grouping'] ?? TimesheetGrouping::None,
            productId: $this->product->id,
            unitId: $this->unit->id,
        );
    }

    public function test_build_excludes_non_billable_and_already_invoiced(): void
    {
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00', 'billable' => false]);
        $existingInvoice = Invoice::create(['number' => 'INV-PRE', 'title' => 'Pre', 'client_id' => $this->client->id, 'currency' => 'BRL', 'total' => 0]);
        $existingItem = $existingInvoice->items()->create(['product_id' => $this->product->id, 'unit_id' => $this->unit->id, 'name' => 'Old', 'quantity' => 1, 'price' => 100, 'total' => 100]);
        $invoiced = $this->makeSession(['start' => '2026-06-11 09:00:00', 'end' => '2026-06-11 10:00:00']);
        $invoiced->update(['invoice_item_id' => $existingItem->id]); // not billable-open
        $this->makeSession(['start' => '2026-06-12 09:00:00', 'end' => '2026-06-12 10:00:00']); // the only open one

        $items = app(SessionInvoiceService::class)->buildDraftItems($this->request());

        $this->assertCount(1, $items);
        $this->assertEqualsWithDelta(100.00, $items->first()->total, 0.001);
    }

    public function test_uniform_rate_group_uses_hours_times_rate(): void
    {
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 11:00:00']); // 120 min @ 100
        $this->makeSession(['start' => '2026-06-11 09:00:00', 'end' => '2026-06-11 10:00:00']); // 60 min @ 100

        $item = app(SessionInvoiceService::class)->buildDraftItems($this->request())->first();

        $this->assertTrue($item->uniformRate);
        $this->assertEqualsWithDelta(3.00, $item->quantity, 0.001); // 180 min = 3h
        $this->assertEqualsWithDelta(100.00, $item->price, 0.001);
        $this->assertEqualsWithDelta(300.00, $item->total, 0.001);
    }

    public function test_mixed_rate_group_falls_back_to_quantity_one(): void
    {
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00', 'rate' => 100.00]); // 100.00
        $this->makeSession(['start' => '2026-06-11 09:00:00', 'end' => '2026-06-11 10:00:00', 'rate' => 50.00]);  // 50.00

        $item = app(SessionInvoiceService::class)->buildDraftItems($this->request())->first();

        $this->assertFalse($item->uniformRate);
        $this->assertEqualsWithDelta(1.0, $item->quantity, 0.001);
        $this->assertEqualsWithDelta(150.00, $item->price, 0.001);
        $this->assertEqualsWithDelta(150.00, $item->total, 0.001);
    }

    public function test_total_reconciles_with_summed_session_value_under_rounding(): void
    {
        // 3 sessions of 20 min @ 100/h: each value = round(100*20/60,2) = 33.33; sum = 99.99.
        // price*quantity would be 100*1.00 = 100.00 — the item total MUST be 99.99 (Σ value), not 100.00.
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 09:20:00']);
        $this->makeSession(['start' => '2026-06-10 10:00:00', 'end' => '2026-06-10 10:20:00']);
        $this->makeSession(['start' => '2026-06-10 11:00:00', 'end' => '2026-06-10 11:20:00']);

        $item = app(SessionInvoiceService::class)->buildDraftItems($this->request())->first();

        $this->assertEqualsWithDelta(99.99, $item->total, 0.001);
    }

    public function test_group_by_project_yields_one_item_per_project(): void
    {
        $alpha = Project::create(['name' => 'Alpha', 'company_id' => $this->user->company->id, 'client_id' => $this->client->id]);
        $beta = Project::create(['name' => 'Beta', 'company_id' => $this->user->company->id, 'client_id' => $this->client->id]);
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00', 'project_id' => $alpha->id]);
        $this->makeSession(['start' => '2026-06-10 11:00:00', 'end' => '2026-06-10 12:00:00', 'project_id' => $beta->id]);

        $items = app(SessionInvoiceService::class)->buildDraftItems($this->request(['grouping' => TimesheetGrouping::Project]));

        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(['Alpha', 'Beta'], $items->pluck('name')->all());
    }

    public function test_none_grouping_uses_period_label_as_name(): void
    {
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 10:00:00']);

        $item = app(SessionInvoiceService::class)->buildDraftItems($this->request())->first();

        $this->assertStringContainsString('2026-06-01', $item->name);
        $this->assertStringContainsString('2026-06-30', $item->name);
    }

    public function test_create_invoice_persists_draft_links_and_freezes_sessions(): void
    {
        $s1 = $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 11:00:00']);
        $s2 = $this->makeSession(['start' => '2026-06-11 09:00:00', 'end' => '2026-06-11 10:00:00']);

        $invoice = app(SessionInvoiceService::class)->createInvoice($this->request(), ['title' => 'June work']);

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatusEnum::DRAFT, $invoice->status);
        $this->assertSame('BRL', $invoice->currency);
        $this->assertEqualsWithDelta(300.00, (float) $invoice->total, 0.001);
        $this->assertCount(1, $invoice->items);
        $this->assertSame($this->product->id, $invoice->items->first()->product_id);

        $itemId = $invoice->items->first()->id;
        $this->assertSame($itemId, $s1->fresh()->invoice_item_id);
        $this->assertSame($itemId, $s2->fresh()->invoice_item_id);
        $this->assertSame(0, WorkSession::query()->billableOpen()->where('client_id', $this->client->id)->count());
    }

    public function test_create_invoice_returns_null_for_empty_pool(): void
    {
        $invoice = app(SessionInvoiceService::class)->createInvoice($this->request([
            'from' => CarbonImmutable::parse('2030-01-01 00:00:00'),
            'to' => CarbonImmutable::parse('2030-01-31 23:59:59'),
        ]), ['title' => 'Nothing']);

        $this->assertNull($invoice);
        $this->assertSame(0, Invoice::count());
    }

    public function test_append_to_invoice_adds_items_recalculates_total_and_freezes(): void
    {
        $invoice = Invoice::create(['number' => 'INV-1', 'title' => 'T', 'client_id' => $this->client->id, 'currency' => 'BRL', 'total' => 0]);
        $session = $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 11:00:00']); // 200.00

        $count = app(SessionInvoiceService::class)->appendToInvoice($invoice, $this->request());

        $this->assertSame(1, $count);
        $this->assertCount(1, $invoice->fresh()->items);
        $this->assertEqualsWithDelta(200.00, (float) $invoice->fresh()->total, 0.001);
        $this->assertNotNull($session->fresh()->invoice_item_id);
    }

    public function test_append_to_invoice_blocks_on_currency_mismatch(): void
    {
        $invoice = Invoice::create(['number' => 'INV-2', 'title' => 'T', 'client_id' => $this->client->id, 'currency' => 'USD', 'total' => 0]);
        $this->makeSession(['start' => '2026-06-10 09:00:00', 'end' => '2026-06-10 11:00:00']);

        $this->expectException(\DomainException::class);
        app(SessionInvoiceService::class)->appendToInvoice($invoice, $this->request());
    }
}
