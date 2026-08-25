<?php

namespace Tests\Feature;

use App\Contracts\InvoiceOverviewReporter;
use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Filament\App\Widgets\InvoicesOverview;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\NullInvoiceOverviewReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NumberFormatter;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class InvoicesOverviewStatsTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->company = $this->actingInCompany($user);
        $this->company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');
        $this->client = Client::create(['name' => 'ACME', 'company_id' => $this->company->id]);

        // These tests characterise the Null implementation's invoice-total semantics
        // as seen through the widget. `InvoiceOverviewReporter` is bound by the open
        // core to `NullInvoiceOverviewReporter`, but a different distribution rebinds
        // it to its own implementation — without this explicit binding, the widget
        // would silently resolve whatever implementation happens to be installed and
        // these tests would describe the wrong class.
        $this->app->instance(InvoiceOverviewReporter::class, new NullInvoiceOverviewReporter(new InvoiceService));
    }

    private function invoice(array $attributes): Invoice
    {
        return Invoice::create(array_merge([
            'number' => 'INV-'.fake()->unique()->numerify('####'),
            'title' => 'x',
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'currency' => 'BRL',
            'total' => 100.00,
            'status' => InvoiceStatusEnum::SENT,
            'due_date' => now()->addDays(5)->toDateString(),
        ], $attributes));
    }

    private function brl(float $value): string
    {
        return NumberFormatter::create(PejotaHelper::getUserLocate(), NumberFormatter::CURRENCY)
            ->formatCurrency($value, 'BRL');
    }

    /**
     * Fixa os QUATRO stats de uma vez, com valores deliberadamente distintos:
     * pending 127 · overdue 20 · due_soon 100 · received 3.
     *
     * As faturas D e E existem para prender o CONJUNTO, não o valor: `UNPAID` e
     * `DRAFT` ficam fora de `pending()` hoje, e é isso que o cloud vai mudar (só
     * para `UNPAID`, e só do lado de lá).
     */
    public function test_the_four_stats_report_invoice_totals_today(): void
    {
        $this->invoice(['total' => 100.00, 'due_date' => now()->addDays(5)->toDateString()]);
        $this->invoice(['total' => 20.00, 'due_date' => now()->subDays(5)->toDateString()]);
        $this->invoice([
            'total' => 7.00,
            'status' => InvoiceStatusEnum::PARTIALLY_PAID,
            'due_date' => now()->addDays(100)->toDateString(),
        ]);
        $this->invoice(['total' => 999.00, 'status' => InvoiceStatusEnum::UNPAID]);
        $this->invoice(['total' => 555.00, 'status' => InvoiceStatusEnum::DRAFT]);
        $this->invoice([
            'total' => 3.00,
            'status' => InvoiceStatusEnum::PAID,
            'payment_date' => now()->subDays(2)->toDateString(),
        ]);
        $this->invoice([
            'total' => 400.00,
            'status' => InvoiceStatusEnum::PAID,
            'payment_date' => now()->subDays(60)->toDateString(),
        ]);

        Livewire::test(InvoicesOverview::class)
            ->assertSee($this->brl(127.0))
            ->assertSee($this->brl(20.0))
            ->assertSee($this->brl(100.0))
            ->assertSee($this->brl(3.0))
            ->assertDontSee($this->brl(999.0))
            ->assertDontSee($this->brl(555.0))
            ->assertDontSee($this->brl(400.0));
    }

    /**
     * Prende a CONTAGEM de não-convertidos, que é a segunda metade do envelope e
     * a que ninguém olha. Sem cotação cadastrada para USD, a fatura estrangeira
     * é contada e excluída do total — nunca somada como zero.
     */
    public function test_unconverted_invoices_are_counted_and_excluded(): void
    {
        $this->invoice(['total' => 100.00, 'currency' => 'BRL']);
        $this->invoice(['total' => 50.00, 'currency' => 'USD']);

        Livewire::test(InvoicesOverview::class)
            ->assertSee($this->brl(100.0))
            ->assertSee(__(':count not converted', ['count' => 1]));
    }
}
