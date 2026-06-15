<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Filament\App\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceFormCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_currency_defaults_to_base_then_follows_selected_client(): void
    {
        $user = User::factory()->create();
        $user->company->settings()->set(CompanySettingsEnum::FINANCE_CURRENCY->value, 'BRL');
        $this->actingAs($user);

        $usdClient = Client::create(['name' => 'US', 'company_id' => $user->company->id, 'currency' => 'USD']);
        $noneClient = Client::create(['name' => 'NA', 'company_id' => $user->company->id, 'currency' => null]);

        Livewire::test(CreateInvoice::class)
            ->assertSet('data.currency', 'BRL')
            ->set('data.client_id', $usdClient->id)
            ->assertSet('data.currency', 'USD')
            ->set('data.client_id', $noneClient->id)
            ->assertSet('data.currency', 'BRL');
    }
}
