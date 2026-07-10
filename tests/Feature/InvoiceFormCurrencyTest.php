<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Filament\App\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class InvoiceFormCurrencyTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    public function test_currency_defaults_to_base_then_follows_selected_client(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);
        $company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');

        $usdClient = Client::create(['name' => 'US', 'company_id' => $company->id, 'currency' => 'USD']);
        $noneClient = Client::create(['name' => 'NA', 'company_id' => $company->id, 'currency' => null]);

        Livewire::test(CreateInvoice::class)
            ->assertSet('data.currency', 'BRL')
            ->set('data.client_id', $usdClient->id)
            ->assertSet('data.currency', 'USD')
            ->set('data.client_id', $noneClient->id)
            ->assertSet('data.currency', 'BRL');
    }

    public function test_creating_invoice_consumes_exactly_one_number_and_assigns_it(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);
        $company->settings()->set(CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value, 'ym000');

        $client = Client::create(['name' => 'ACME', 'company_id' => $company->id]);

        $expectedNumber = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->peekNextDocNumberFormated();

        Livewire::test(CreateInvoice::class)
            ->set('data.client_id', $client->id)
            ->set('data.title', 'First invoice')
            ->set('data.total', 100)
            ->set('data.items', [])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('invoices', [
            'company_id' => $company->id,
            'number' => $expectedNumber,
        ]);

        $this->assertSame(
            1,
            $company->settings()->get(CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->value)
        );
    }
}
