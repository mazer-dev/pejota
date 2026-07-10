<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Models\Client;
use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class InvoiceBaseTotalTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private function actingUserWithBase(string $base): User
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);
        $company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, $base);

        return $user;
    }

    private function companyOf(User $user): Company
    {
        return $user->companies()->firstOrFail();
    }

    private function makeInvoice(User $user, array $attributes): Invoice
    {
        $client = Client::create(['name' => 'C', 'company_id' => $this->companyOf($user)->id]);

        return Invoice::create(array_merge([
            'number' => 'INV-'.fake()->unique()->numerify('####'),
            'title' => 'Invoice',
            'client_id' => $client->id,
            'company_id' => $this->companyOf($user)->id,
            'total' => 100.00,
            'status' => InvoiceStatusEnum::SENT,
        ], $attributes));
    }

    public function test_base_total_returns_total_when_document_currency_equals_base(): void
    {
        $user = $this->actingUserWithBase('USD');
        $invoice = $this->makeInvoice($user, ['currency' => 'USD']);

        $this->assertEqualsWithDelta(100.00, $invoice->baseTotal, 0.001);
    }

    public function test_base_total_converts_live_for_pending_foreign_invoice(): void
    {
        $user = $this->actingUserWithBase('BRL');
        ExchangeRate::factory()->forCurrency('BRL')->on(now()->toDateString())->create(['rate' => 5.0]);
        $invoice = $this->makeInvoice($user, ['currency' => 'USD', 'status' => InvoiceStatusEnum::SENT]);

        $this->assertEqualsWithDelta(500.00, $invoice->baseTotal, 0.001);
    }

    public function test_base_total_uses_frozen_rate_when_paid(): void
    {
        $user = $this->actingUserWithBase('BRL');
        ExchangeRate::factory()->forCurrency('BRL')->on(now()->toDateString())->create(['rate' => 5.0]);
        $invoice = $this->makeInvoice($user, [
            'currency' => 'USD',
            'status' => InvoiceStatusEnum::PAID,
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertNotNull($invoice->exchange_rate);
        $this->assertEqualsWithDelta(500.00, $invoice->baseTotal, 0.001);
    }

    public function test_saving_hook_sets_rate_one_for_paid_base_currency(): void
    {
        $user = $this->actingUserWithBase('USD');
        $invoice = $this->makeInvoice($user, [
            'currency' => 'USD',
            'status' => InvoiceStatusEnum::PAID,
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertEqualsWithDelta(1.0, (float) $invoice->exchange_rate, 0.0000001);
    }

    public function test_saving_hook_leaves_rate_null_when_no_quote_available(): void
    {
        $user = $this->actingUserWithBase('BRL');
        $invoice = $this->makeInvoice($user, [
            'currency' => 'USD',
            'status' => InvoiceStatusEnum::PAID,
            'payment_date' => now()->toDateString(),
        ]);

        $this->assertNull($invoice->exchange_rate);
    }

    public function test_saving_hook_preserves_manual_rate(): void
    {
        $user = $this->actingUserWithBase('BRL');
        $invoice = $this->makeInvoice($user, [
            'currency' => 'USD',
            'status' => InvoiceStatusEnum::PAID,
            'payment_date' => now()->toDateString(),
            'exchange_rate' => 5.42,
        ]);

        $this->assertEqualsWithDelta(5.42, (float) $invoice->exchange_rate, 0.0000001);
    }

    public function test_saving_hook_clears_rate_when_leaving_paid(): void
    {
        $user = $this->actingUserWithBase('BRL');
        ExchangeRate::factory()->forCurrency('BRL')->on(now()->toDateString())->create(['rate' => 5.0]);
        $invoice = $this->makeInvoice($user, [
            'currency' => 'USD',
            'status' => InvoiceStatusEnum::PAID,
            'payment_date' => now()->toDateString(),
        ]);
        $this->assertNotNull($invoice->exchange_rate);

        $invoice->update(['status' => InvoiceStatusEnum::PARTIALLY_PAID]);

        $this->assertNull($invoice->fresh()->exchange_rate);
    }

    public function test_backfill_sets_each_companys_base_currency_on_null_currency_invoices(): void
    {
        $userBrl = User::factory()->create();
        $this->companyOf($userBrl)->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');
        $userEur = User::factory()->create();
        $this->companyOf($userEur)->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'EUR');

        $clientBrl = Client::create(['name' => 'A', 'company_id' => $this->companyOf($userBrl)->id]);
        $clientEur = Client::create(['name' => 'B', 'company_id' => $this->companyOf($userEur)->id]);

        $idBrl = DB::table('invoices')->insertGetId([
            'number' => 'B1', 'title' => 'x', 'client_id' => $clientBrl->id,
            'company_id' => $this->companyOf($userBrl)->id, 'total' => 100, 'status' => 'sent',
            'currency' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $idEur = DB::table('invoices')->insertGetId([
            'number' => 'E1', 'title' => 'y', 'client_id' => $clientEur->id,
            'company_id' => $this->companyOf($userEur)->id, 'total' => 100, 'status' => 'sent',
            'currency' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        (new InvoiceService)->backfillNullCurrencies();

        $this->assertSame('BRL', DB::table('invoices')->where('id', $idBrl)->value('currency'));
        $this->assertSame('EUR', DB::table('invoices')->where('id', $idEur)->value('currency'));
    }

    public function test_backfill_does_not_overwrite_invoices_that_already_have_a_currency(): void
    {
        $user = User::factory()->create();
        $this->companyOf($user)->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');
        $client = Client::create(['name' => 'C', 'company_id' => $this->companyOf($user)->id]);

        $id = DB::table('invoices')->insertGetId([
            'number' => 'X1', 'title' => 'z', 'client_id' => $client->id,
            'company_id' => $this->companyOf($user)->id, 'total' => 50, 'status' => 'draft',
            'currency' => 'EUR', 'created_at' => now(), 'updated_at' => now(),
        ]);

        (new InvoiceService)->backfillNullCurrencies();

        $this->assertSame('EUR', DB::table('invoices')->where('id', $id)->value('currency'));
    }

    public function test_backfill_defaults_to_usd_when_company_has_no_currency_setting(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'D', 'company_id' => $this->companyOf($user)->id]);

        $id = DB::table('invoices')->insertGetId([
            'number' => 'U1', 'title' => 'w', 'client_id' => $client->id,
            'company_id' => $this->companyOf($user)->id, 'total' => 70, 'status' => 'sent',
            'currency' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        (new InvoiceService)->backfillNullCurrencies();

        $this->assertSame('USD', DB::table('invoices')->where('id', $id)->value('currency'));
    }
}
