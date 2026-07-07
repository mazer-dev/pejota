<?php

namespace Tests\Feature\Sending;

use App\Filament\App\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Filament\App\Resources\InvoiceResource\RelationManagers\DeliveriesRelationManager;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class DeliveriesRelationManagerTest extends TestCase
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

    public function test_lists_deliveries_for_invoice(): void
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->user->company->id]);
        $invoice = Invoice::create([
            'number' => 'INV-1', 'title' => 'X', 'client_id' => $client->id,
            'company_id' => $this->user->company->id, 'total' => 100, 'currency' => 'USD', 'status' => 'draft',
        ]);
        $delivery = $invoice->deliveries()->create([
            'created_by' => $this->user->id, 'channel' => 'email', 'status' => 'sent',
            'to' => ['a@acme.test'], 'subject' => 'Hello subject', 'attach_invoice_pdf' => true, 'sent_at' => now(),
        ]);

        Livewire::test(DeliveriesRelationManager::class, [
            'ownerRecord' => $invoice,
            'pageClass' => ViewInvoice::class,
        ])
            ->assertCanSeeTableRecords([$delivery])
            ->assertSee('Hello subject');
    }
}
