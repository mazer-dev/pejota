<?php

namespace Tests\Feature\Sending;

use App\Enums\DeliveryStatusEnum;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class InvoiceDeliveryModelTest extends TestCase
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

    private function makeInvoice(): Invoice
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->user->company->id]);

        return Invoice::create([
            'number' => 'INV-1', 'title' => 'X', 'client_id' => $client->id,
            'company_id' => $this->user->company->id, 'total' => 10, 'currency' => 'USD', 'status' => 'draft',
        ]);
    }

    public function test_invoice_has_many_deliveries_with_casts(): void
    {
        $invoice = $this->makeInvoice();

        $delivery = $invoice->deliveries()->create([
            'created_by' => $this->user->id,
            'channel' => 'email',
            'status' => 'queued',
            'to' => ['a@acme.test', 'b@acme.test'],
            'subject' => 'Hi',
            'attach_invoice_pdf' => true,
        ]);

        $delivery = $delivery->fresh();
        $this->assertSame(DeliveryStatusEnum::Queued, $delivery->status);
        $this->assertSame(['a@acme.test', 'b@acme.test'], $delivery->to);
        $this->assertTrue($delivery->attach_invoice_pdf);
        $this->assertSame($delivery->id, $invoice->fresh()->deliveries->first()->id);
    }

    public function test_notifications_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('notifications'));
    }

    public function test_delivery_is_tenant_scoped(): void
    {
        $this->makeInvoice()->deliveries()->create([
            'created_by' => $this->user->id, 'channel' => 'email', 'status' => 'queued',
            'to' => ['a@acme.test'], 'subject' => 'Hi',
        ]);

        $other = User::factory()->create();
        Landlord::addTenant('company_id', $other->company->id);

        $this->assertSame(0, InvoiceDelivery::query()->count());
    }
}
