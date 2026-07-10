<?php

namespace Tests\Feature\Sending;

use App\Filament\App\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Filament\App\Resources\InvoiceResource\RelationManagers\DeliveriesRelationManager;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class DeliveriesRelationManagerTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
    }

    public function test_lists_deliveries_for_invoice(): void
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id]);
        $invoice = Invoice::create([
            'number' => 'INV-1', 'title' => 'X', 'client_id' => $client->id,
            'company_id' => $this->company->id, 'total' => 100, 'currency' => 'USD', 'status' => 'draft',
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
