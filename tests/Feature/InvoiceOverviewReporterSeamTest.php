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
use App\Services\NullInvoiceOverviewReporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NumberFormatter;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class InvoiceOverviewReporterSeamTest extends TestCase
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
    }

    public function test_the_open_core_binds_the_null_reporter(): void
    {
        $this->assertInstanceOf(
            NullInvoiceOverviewReporter::class,
            app(InvoiceOverviewReporter::class),
        );
    }

    public function test_the_envelope_has_the_four_keys_even_with_no_invoices(): void
    {
        $summary = app(InvoiceOverviewReporter::class)->summary(
            $this->company,
            'BRL',
            'America/Sao_Paulo',
            CarbonImmutable::now('America/Sao_Paulo')->startOfDay(),
            30,
            30,
        );

        $this->assertSame(
            ['pending', 'overdue', 'due_soon', 'received'],
            array_keys($summary),
        );

        foreach ($summary as $stat) {
            $this->assertSame(['total' => 0.0, 'unconverted' => 0], $stat);
        }
    }

    public function test_the_null_reporter_reproduces_the_invoice_total_semantics(): void
    {
        Invoice::create([
            'number' => 'INV-1', 'title' => 'x', 'client_id' => $this->client->id,
            'company_id' => $this->company->id, 'currency' => 'BRL', 'total' => 100.00,
            'status' => InvoiceStatusEnum::SENT,
            'due_date' => CarbonImmutable::now()->addDays(5)->toDateString(),
        ]);

        $summary = app(InvoiceOverviewReporter::class)->summary(
            $this->company,
            'BRL',
            'America/Sao_Paulo',
            CarbonImmutable::now('America/Sao_Paulo')->startOfDay(),
            30,
            30,
        );

        $this->assertSame(100.0, $summary['pending']['total']);
        $this->assertSame(100.0, $summary['due_soon']['total']);
        $this->assertSame(0.0, $summary['overdue']['total']);
        $this->assertSame(0.0, $summary['received']['total']);
    }

    /**
     * Sem NENHUMA fatura no banco. Se o widget ainda somasse por conta própria, os
     * quatro números viriam zero — é a única forma de provar que ele passou a ler do
     * contrato em vez de continuar acertando por coincidência.
     */
    public function test_the_widget_renders_whatever_the_reporter_returns(): void
    {
        $fake = new class implements InvoiceOverviewReporter
        {
            public Company $capturedCompany;

            public string $capturedBaseCurrency;

            public string $capturedTimezone;

            public CarbonImmutable $capturedToday;

            public int $capturedDueWithinDays;

            public int $capturedReceivedDays;

            public function summary(
                Company $company,
                string $baseCurrency,
                string $timezone,
                CarbonImmutable $today,
                int $dueWithinDays,
                int $receivedDays,
            ): array {
                $this->capturedCompany = $company;
                $this->capturedBaseCurrency = $baseCurrency;
                $this->capturedTimezone = $timezone;
                $this->capturedToday = $today;
                $this->capturedDueWithinDays = $dueWithinDays;
                $this->capturedReceivedDays = $receivedDays;

                return [
                    'pending' => ['total' => 11.0, 'unconverted' => 0],
                    'overdue' => ['total' => 22.0, 'unconverted' => 3],
                    'due_soon' => ['total' => 33.0, 'unconverted' => 0],
                    'received' => ['total' => 44.0, 'unconverted' => 0],
                ];
            }
        };

        $this->app->instance(InvoiceOverviewReporter::class, $fake);

        $fmt = NumberFormatter::create(PejotaHelper::getUserLocate(), NumberFormatter::CURRENCY);

        Livewire::test(InvoicesOverview::class)
            ->assertSee($fmt->formatCurrency(11.0, 'BRL'))
            ->assertSee($fmt->formatCurrency(22.0, 'BRL'))
            ->assertSee($fmt->formatCurrency(33.0, 'BRL'))
            ->assertSee($fmt->formatCurrency(44.0, 'BRL'))
            ->assertSee(__(':count not converted', ['count' => 3]));

        $this->assertSame(30, $fake->capturedDueWithinDays);
        $this->assertSame(30, $fake->capturedReceivedDays);
        $this->assertSame('BRL', $fake->capturedBaseCurrency);
        $this->assertSame($this->company->getKey(), $fake->capturedCompany->getKey());
    }
}
