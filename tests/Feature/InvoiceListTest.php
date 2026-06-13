<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Filament\App\Resources\InvoiceResource;
use App\Filament\App\Widgets\InvoicesOverview;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->client = Client::create([
            'name' => 'Acme',
            'company_id' => $this->user->company->id,
        ]);
    }

    private function makeInvoice(string $number, InvoiceStatusEnum $status, ?string $dueDate, ?string $paymentDate = null, float $total = 100.00): Invoice
    {
        return Invoice::create([
            'number' => $number,
            'title' => $number,
            'status' => $status,
            'client_id' => $this->client->id,
            'due_date' => $dueDate,
            'payment_date' => $paymentDate,
            'total' => $total,
            'company_id' => $this->user->company->id,
        ]);
    }

    public function test_overview_widget_shows_total_pending(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        // PARTIALLY_PAID due in 60 days: counts as "pending" in the new widget,
        // but the OLD widget (which only summed SENT) would show $0.00 here.
        // Due > 30 days keeps overdue/due-soon/received at zero, isolating "pending".
        $this->makeInvoice('P1', InvoiceStatusEnum::PARTIALLY_PAID, $today->addDays(60)->toDateString(), null, 190.00);

        Livewire::test(InvoicesOverview::class)
            ->assertOk()
            ->assertSee('$190.00')
            ->assertSee('$0.00');
    }

    public function test_default_tab_is_pending_and_filters_records(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $sent = $this->makeInvoice('S1', InvoiceStatusEnum::SENT, $today->addDays(5)->toDateString());
        $draft = $this->makeInvoice('D1', InvoiceStatusEnum::DRAFT, $today->toDateString());

        Livewire::test(\App\Filament\App\Resources\InvoiceResource\Pages\ListInvoices::class)
            ->assertOk()
            ->assertSet('activeTab', 'pending')
            ->assertCanSeeTableRecords([$sent])
            ->assertCanNotSeeTableRecords([$draft]);
    }

    public function test_overdue_tab_shows_only_overdue_pending(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $overdue = $this->makeInvoice('OD', InvoiceStatusEnum::SENT, $today->subDay()->toDateString());
        $future = $this->makeInvoice('FU', InvoiceStatusEnum::SENT, $today->addDay()->toDateString());

        Livewire::test(\App\Filament\App\Resources\InvoiceResource\Pages\ListInvoices::class)
            ->set('activeTab', 'overdue')
            ->assertCanSeeTableRecords([$overdue])
            ->assertCanNotSeeTableRecords([$future]);
    }

    public function test_delinquent_tab_shows_only_unpaid(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $unpaid = $this->makeInvoice('U1', InvoiceStatusEnum::UNPAID, $today->toDateString());
        $sent = $this->makeInvoice('S2', InvoiceStatusEnum::SENT, $today->toDateString());

        Livewire::test(\App\Filament\App\Resources\InvoiceResource\Pages\ListInvoices::class)
            ->set('activeTab', 'delinquent')
            ->assertCanSeeTableRecords([$unpaid])
            ->assertCanNotSeeTableRecords([$sent]);
    }

    public function test_filter_by_status(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $sent = $this->makeInvoice('S1', InvoiceStatusEnum::SENT, $today->addDay()->toDateString());
        $paid = $this->makeInvoice('P1', InvoiceStatusEnum::PAID, $today->toDateString(), $today->toDateString());

        Livewire::test(\App\Filament\App\Resources\InvoiceResource\Pages\ListInvoices::class)
            ->set('activeTab', 'all')
            ->set('tableFilters.status.values', [InvoiceStatusEnum::PAID->value])
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$sent]);
    }

    public function test_filter_by_client(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $other = Client::create(['name' => 'Other', 'company_id' => $this->user->company->id]);

        $a = $this->makeInvoice('A1', InvoiceStatusEnum::SENT, $today->addDay()->toDateString());
        $b = Invoice::create([
            'number' => 'B1', 'title' => 'B1', 'status' => InvoiceStatusEnum::SENT,
            'client_id' => $other->id, 'due_date' => $today->addDay()->toDateString(),
            'total' => 100.00, 'company_id' => $this->user->company->id,
        ]);

        Livewire::test(\App\Filament\App\Resources\InvoiceResource\Pages\ListInvoices::class)
            ->set('activeTab', 'all')
            ->set('tableFilters.client.value', $this->client->id)
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b]);
    }

    public function test_month_year_group_expression_per_driver(): void
    {
        $this->assertSame(
            "strftime('%Y-%m', due_date)",
            InvoiceResource::monthYearGroupExpression('sqlite', 'due_date')
        );
        $this->assertSame(
            "to_char(due_date, 'YYYY-MM')",
            InvoiceResource::monthYearGroupExpression('pgsql', 'due_date')
        );
        $this->assertSame(
            "DATE_FORMAT(due_date, '%Y-%m')",
            InvoiceResource::monthYearGroupExpression('mysql', 'due_date')
        );
    }

    public function test_list_renders_when_grouped_by_due_date(): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        $a = $this->makeInvoice('M1', InvoiceStatusEnum::SENT, $today->toDateString());
        $b = $this->makeInvoice('M2', InvoiceStatusEnum::SENT, $today->subMonth()->toDateString());

        $titleA = $a->due_date->translatedFormat('F Y');
        $titleB = $b->due_date->translatedFormat('F Y');

        $this->assertNotSame($titleA, $titleB, 'The two invoices must fall in different calendar months for this test to be meaningful.');

        Livewire::test(\App\Filament\App\Resources\InvoiceResource\Pages\ListInvoices::class)
            ->set('activeTab', 'all')
            ->set('tableGrouping', 'due_date')
            ->assertOk()
            ->assertCanSeeTableRecords([$a, $b])
            ->assertSee($titleA)
            ->assertSee($titleB);
    }
}
