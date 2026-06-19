<?php

namespace Tests\Feature\Invoicing;

use App\Enums\CompanySettingsEnum;
use App\Filament\App\Resources\WorkSessionResource\Pages\ListWorkSessions;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class GenerateInvoiceActionTest extends TestCase
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

    private function data(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'from' => now()->subMonth()->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
            'grouping' => 'none',
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'title' => 'June work',
        ], $overrides);
    }

    public function test_action_requires_a_client(): void
    {
        Livewire::test(ListWorkSessions::class)
            ->callAction('generateInvoice', data: $this->data(['client_id' => null]))
            ->assertHasActionErrors(['client_id' => ['required']]);
    }

    public function test_action_creates_draft_invoice_and_persists_setting_defaults(): void
    {
        WorkSession::create([
            'title' => 'Work', 'company_id' => $this->user->company->id, 'client_id' => $this->client->id,
            'is_running' => false, 'rate' => 100.00, 'billable' => true,
            'start' => now()->subDays(2), 'end' => now()->subDays(2)->addHour(),
        ]);

        Livewire::test(ListWorkSessions::class)
            ->callAction('generateInvoice', data: $this->data())
            ->assertHasNoActionErrors();

        $this->assertSame(1, Invoice::count());
        $this->assertSame(
            $this->product->id,
            (int) $this->user->company->settings()->get(CompanySettingsEnum::INVOICE_SESSION_PRODUCT->value)
        );
    }

    public function test_action_does_not_create_invoice_for_empty_pool(): void
    {
        Livewire::test(ListWorkSessions::class)
            ->callAction('generateInvoice', data: $this->data([
                'from' => '2030-01-01', 'to' => '2030-01-31',
            ]))
            ->assertHasNoActionErrors();

        $this->assertSame(0, Invoice::count());
    }
}
