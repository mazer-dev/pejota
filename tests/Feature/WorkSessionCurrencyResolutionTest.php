<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\BackfillWorkSessionCurrency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

/**
 * `work_sessions.currency` tem default `'USD'` no schema, e até 2026-08-07 a
 * resolução da moeda vivia só em três pontos de UI — nunca no model. Qualquer
 * outro caminho de criação gravava `'USD'` numa empresa que pode ser de qualquer
 * moeda, sem ninguém ter escolhido isso. É o risco R3c do detalhamento da E6a.
 */
class WorkSessionCurrencyResolutionTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private function companyInBrl(): Company
    {
        $company = $this->actingInCompany(User::factory()->create());
        $company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');

        return $company;
    }

    public function test_a_session_created_outside_the_ui_gets_the_company_currency(): void
    {
        $company = $this->companyInBrl();

        $session = WorkSession::create([
            'company_id' => $company->id,
            'start' => '2026-03-10 09:00:00',
            'end' => '2026-03-10 10:00:00',
            'is_running' => false,
        ]);

        $this->assertSame('BRL', $session->refresh()->currency, 'caiu no default USD do schema');
    }

    public function test_the_client_currency_wins_over_the_company_one(): void
    {
        $company = $this->companyInBrl();
        $client = Client::create(['name' => 'Estrangeiro', 'company_id' => $company->id, 'currency' => 'EUR']);

        $session = WorkSession::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'start' => '2026-03-10 09:00:00',
            'end' => '2026-03-10 10:00:00',
            'is_running' => false,
        ]);

        $this->assertSame('EUR', $session->refresh()->currency);
    }

    public function test_an_explicit_currency_is_never_overwritten(): void
    {
        $company = $this->companyInBrl();

        $session = WorkSession::create([
            'company_id' => $company->id,
            'start' => '2026-03-10 09:00:00',
            'end' => '2026-03-10 10:00:00',
            'is_running' => false,
            'currency' => 'JPY',
        ]);

        $this->assertSame('JPY', $session->refresh()->currency, 'a escolha explícita do usuário foi sobrescrita');
    }

    public function test_the_backfill_fixes_a_valueless_session_stamped_with_the_schema_default(): void
    {
        $company = $this->companyInBrl();
        $session = WorkSession::create([
            'company_id' => $company->id,
            'start' => '2026-03-10 09:00:00',
            'end' => '2026-03-10 10:00:00',
            'is_running' => false,
        ]);
        // Simula o histórico: gravado direto, como o schema deixava.
        DB::table('work_sessions')->where('id', $session->id)->update(['currency' => 'USD', 'value' => 0]);

        $resultado = (new BackfillWorkSessionCurrency)();

        $this->assertSame(1, $resultado['updated']);
        $this->assertSame('BRL', $session->refresh()->currency);
    }

    /**
     * A guarda que define o serviço: sessão COM valor é dinheiro, e dinheiro não
     * se reinterpreta em silêncio. Ela é contada e devolvida para decisão humana.
     */
    public function test_the_backfill_refuses_to_touch_a_session_that_carries_money(): void
    {
        $company = $this->companyInBrl();
        $session = WorkSession::create([
            'company_id' => $company->id,
            'start' => '2026-03-10 09:00:00',
            'end' => '2026-03-10 10:00:00',
            'is_running' => false,
        ]);
        DB::table('work_sessions')->where('id', $session->id)->update(['currency' => 'USD', 'value' => 50000]);

        $resultado = (new BackfillWorkSessionCurrency)();

        $this->assertSame(0, $resultado['updated']);
        $this->assertSame(1, $resultado['skipped_with_value']);
        $this->assertSame('USD', $session->refresh()->currency, 'sessão com dinheiro foi reinterpretada');
    }

    public function test_the_backfill_is_idempotent(): void
    {
        $company = $this->companyInBrl();
        $session = WorkSession::create([
            'company_id' => $company->id,
            'start' => '2026-03-10 09:00:00',
            'end' => '2026-03-10 10:00:00',
            'is_running' => false,
        ]);
        DB::table('work_sessions')->where('id', $session->id)->update(['currency' => 'USD', 'value' => 0]);

        (new BackfillWorkSessionCurrency)();
        $segunda = (new BackfillWorkSessionCurrency)();

        $this->assertSame(0, $segunda['updated']);
        $this->assertSame('BRL', $session->refresh()->currency);
    }
}
