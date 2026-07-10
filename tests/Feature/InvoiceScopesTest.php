<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class InvoiceScopesTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);

        $this->client = Client::create([
            'name' => 'Acme',
            'company_id' => $this->company->id,
        ]);
    }

    private function makeInvoice(string $number, InvoiceStatusEnum $status, ?string $dueDate, ?string $paymentDate = null): Invoice
    {
        return Invoice::create([
            'number' => $number,
            'title' => $number,
            'status' => $status,
            'client_id' => $this->client->id,
            'due_date' => $dueDate,
            'payment_date' => $paymentDate,
            'total' => 100.00,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_pending_scope_includes_only_sent_and_partially_paid(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $sent = $this->makeInvoice('SENT-1', InvoiceStatusEnum::SENT, $today->addDays(5)->toDateString());
        $partial = $this->makeInvoice('PART-1', InvoiceStatusEnum::PARTIALLY_PAID, $today->addDays(5)->toDateString());
        $this->makeInvoice('DRAFT-1', InvoiceStatusEnum::DRAFT, $today->toDateString());
        $this->makeInvoice('PAID-1', InvoiceStatusEnum::PAID, $today->toDateString(), $today->toDateString());
        $this->makeInvoice('UNPAID-1', InvoiceStatusEnum::UNPAID, $today->toDateString());
        $this->makeInvoice('CANCEL-1', InvoiceStatusEnum::CANCELED, $today->toDateString());

        $ids = Invoice::pending()->pluck('id')->sort()->values()->all();

        $this->assertEquals([$sent->id, $partial->id], $ids);
    }

    public function test_overdue_scope_is_pending_and_before_today(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $overdue = $this->makeInvoice('OD-1', InvoiceStatusEnum::SENT, $today->subDay()->toDateString());
        $this->makeInvoice('FUT-1', InvoiceStatusEnum::SENT, $today->addDay()->toDateString());
        $this->makeInvoice('UNPAID-OD', InvoiceStatusEnum::UNPAID, $today->subDay()->toDateString());
        $dueToday = $this->makeInvoice('TODAY', InvoiceStatusEnum::SENT, $today->toDateString());

        $ids = Invoice::overdue()->pluck('id')->all();

        $this->assertNotContains($dueToday->id, $ids);
        $this->assertEquals([$overdue->id], $ids);
    }

    public function test_due_within_scope_includes_today_through_n_days(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $dueToday = $this->makeInvoice('DT', InvoiceStatusEnum::SENT, $today->toDateString());
        $dueIn30 = $this->makeInvoice('D30', InvoiceStatusEnum::SENT, $today->addDays(30)->toDateString());
        $this->makeInvoice('D31', InvoiceStatusEnum::SENT, $today->addDays(31)->toDateString());
        $this->makeInvoice('OD', InvoiceStatusEnum::SENT, $today->subDay()->toDateString());

        $ids = Invoice::dueWithin(30)->pluck('id')->sort()->values()->all();

        $this->assertEquals([$dueToday->id, $dueIn30->id], $ids);
    }

    public function test_delinquent_scope_includes_only_unpaid(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $unpaid = $this->makeInvoice('U1', InvoiceStatusEnum::UNPAID, $today->toDateString());
        $this->makeInvoice('S1', InvoiceStatusEnum::SENT, $today->toDateString());

        $ids = Invoice::delinquent()->pluck('id')->all();

        $this->assertEquals([$unpaid->id], $ids);
    }

    public function test_received_between_scope_filters_paid_by_payment_date(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $inWindow = $this->makeInvoice('P1', InvoiceStatusEnum::PAID, $today->subDays(40)->toDateString(), $today->subDays(10)->toDateString());
        $this->makeInvoice('P2', InvoiceStatusEnum::PAID, $today->subDays(40)->toDateString(), $today->subDays(40)->toDateString());
        $this->makeInvoice('S2', InvoiceStatusEnum::SENT, $today->toDateString(), null);

        $ids = Invoice::receivedBetween($today->subDays(30), $today)->pluck('id')->all();

        $this->assertEquals([$inWindow->id], $ids);
    }
}
