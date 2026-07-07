<?php

namespace Tests\Feature\Sending;

use App\Enums\DeliveryStatusEnum;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoicing\InvoiceDeliveryComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class InvoiceDeliveryComposerTest extends TestCase
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
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->user->company->id, 'currency' => 'USD']);

        return Invoice::create([
            'number' => 'INV-1', 'title' => 'X', 'client_id' => $client->id,
            'company_id' => $this->user->company->id, 'total' => 100, 'currency' => 'USD',
            'due_date' => '2026-05-15', 'status' => 'draft',
        ]);
    }

    public function test_composes_queued_delivery_snapshot(): void
    {
        $invoice = $this->makeInvoice();

        $delivery = app(InvoiceDeliveryComposer::class)->compose($invoice, [
            'to' => ['a@acme.test'],
            'cc' => ['c@acme.test'],
            'subject' => 'Invoice INV-1',
            'body' => '<p>Hi</p>',
            'signature' => '<p>Me</p>',
            'attach_invoice_pdf' => true,
            'attach_timesheet' => false,
            'external_file_path' => null,
        ], $this->user->id);

        $this->assertSame(DeliveryStatusEnum::Queued, $delivery->status);
        $this->assertSame(['a@acme.test'], $delivery->to);
        $this->assertSame(['c@acme.test'], $delivery->cc);
        $this->assertNull($delivery->timesheet_params);
        $this->assertSame($this->user->id, $delivery->created_by);
    }

    public function test_builds_timesheet_params_when_attach_timesheet(): void
    {
        $invoice = $this->makeInvoice();

        $delivery = app(InvoiceDeliveryComposer::class)->compose($invoice, [
            'to' => ['a@acme.test'],
            'subject' => 'S', 'body' => '<p>b</p>', 'signature' => '<p>s</p>',
            'attach_invoice_pdf' => true,
            'attach_timesheet' => true,
            'timesheet_from' => '2026-05-01',
            'timesheet_to' => '2026-05-31',
            'timesheet_layout' => 'client',
            'external_file_path' => null,
        ], $this->user->id);

        $this->assertIsArray($delivery->timesheet_params);
        $this->assertSame($invoice->client_id, $delivery->timesheet_params['clientId']);
        $this->assertSame('client', $delivery->timesheet_params['layoutKey']);
    }
}
