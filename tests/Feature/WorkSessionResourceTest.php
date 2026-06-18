<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Filament\App\Resources\WorkSessionResource;
use App\Filament\App\Resources\WorkSessionResource\Pages\CreateWorkSession;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class WorkSessionResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Landlord::addTenant('company_id', $this->user->company->id);
    }

    public function test_list_page_loads(): void
    {
        $this->get(WorkSessionResource::getUrl('index'))
            ->assertOk();
    }

    public function test_selecting_client_cascades_rate_and_currency_into_the_form(): void
    {
        $client = Client::create([
            'name' => 'Acme',
            'company_id' => $this->user->company->id,
            'currency' => 'BRL',
            'default_hourly_rate' => 90.00,
            'billable_default' => true,
        ]);

        // fillForm does not trigger afterStateUpdated hooks in Filament v3 tests,
        // so we verify the cascade via the persisted record instead of assertFormSet.
        Livewire::test(CreateWorkSession::class)
            ->fillForm([
                'title' => 'Cascade',
                'client' => $client->id,
                'start' => '2026-06-17 09:00',
                'end' => '2026-06-17 10:00',
                'duration' => 60,
                'is_running' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $session = WorkSession::where('title', 'Cascade')->first();
        $this->assertNotNull($session);
        $this->assertEquals(90.00, $session->rate);
        $this->assertSame('BRL', $session->currency);
        $this->assertEquals(90.00, $session->value); // 90/h * 1h
    }

    public function test_clone_does_not_carry_over_invoice_item_link(): void
    {
        $client = Client::create([
            'name' => 'Clone Client',
            'company_id' => $this->user->company->id,
        ]);
        $unit = Unit::create([
            'name' => 'Hour',
            'symbol' => 'h',
            'company_id' => $this->user->company->id,
        ]);
        $product = Product::create([
            'name' => 'Service',
            'service' => true,
            'digital' => false,
            'company_id' => $this->user->company->id,
            'unit_id' => $unit->id,
        ]);
        $invoice = Invoice::create([
            'number' => 'INV-CLONE',
            'title' => 'Inv',
            'status' => InvoiceStatusEnum::DRAFT,
            'company_id' => $this->user->company->id,
            'client_id' => $client->id,
            'total' => 10.00,
        ]);
        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'name' => 'line',
            'quantity' => 1,
            'price' => 10.00,
            'discount' => 0,
            'total' => 10.00,
        ]);
        $source = WorkSession::create([
            'title' => 'Invoiced source',
            'company_id' => $this->user->company->id,
            'start' => '2026-06-17 09:00:00',
            'end' => '2026-06-17 10:00:00',
            'is_running' => false,
            'rate' => 10.00,
            'invoice_item_id' => $item->id,
        ]);

        WorkSessionResource::clone($source);

        $clone = WorkSession::where('title', 'Invoiced source')
            ->where('id', '!=', $source->id)
            ->first();

        $this->assertNotNull($clone);
        $this->assertNull($clone->invoice_item_id);
    }

    public function test_selecting_client_cascades_billable_into_the_form(): void
    {
        $client = Client::create([
            'name' => 'NonBillable Co',
            'company_id' => $this->user->company->id,
            'currency' => 'BRL',
            'default_hourly_rate' => 40.00,
            'billable_default' => false,
        ]);

        Livewire::test(CreateWorkSession::class)
            ->set('data.client', $client->id)
            ->assertFormSet([
                'billable' => false,
                'rate' => 40.00,
                'currency' => 'BRL',
            ]);
    }

    public function test_manual_billable_choice_is_not_overridden_for_billable_client(): void
    {
        $client = Client::create([
            'name' => 'Billable Co',
            'company_id' => $this->user->company->id,
            'currency' => 'BRL',
            'default_hourly_rate' => 40.00,
            'billable_default' => true,
        ]);

        Livewire::test(CreateWorkSession::class)
            ->fillForm([
                'title' => 'Pro bono hour',
                'client' => $client->id,
                'start' => '2026-06-17 09:00',
                'end' => '2026-06-17 10:00',
                'duration' => 60,
                'is_running' => false,
                'rate' => 40.00,
                'billable' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $session = WorkSession::where('title', 'Pro bono hour')->first();
        $this->assertNotNull($session);
        $this->assertFalse($session->billable, 'A deliberate billable=false must survive save even for a billable client');
    }

    public function test_end_before_start_shows_form_error(): void
    {
        Livewire::test(CreateWorkSession::class)
            ->fillForm([
                'title' => 'Bad time',
                'start' => '2026-06-17 11:00',
                'end' => '2026-06-17 09:00',
                'is_running' => false,
                'rate' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['end']);
    }
}
