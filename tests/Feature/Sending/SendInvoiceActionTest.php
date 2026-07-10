<?php

namespace Tests\Feature\Sending;

use App\Filament\App\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Jobs\SendInvoiceDelivery;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class SendInvoiceActionTest extends TestCase
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

    private function makeInvoice(): Invoice
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id, 'email' => 'main@acme.test']);

        return Invoice::create([
            'number' => 'INV-1', 'title' => 'X', 'client_id' => $client->id,
            'company_id' => $this->company->id, 'total' => 100, 'currency' => 'USD',
            'due_date' => '2026-05-15', 'status' => 'draft',
        ]);
    }

    private function withMailConfig(): void
    {
        $this->company->mailConfig()->create([
            'host' => 'smtp.example.test', 'port' => 587, 'username' => 'u', 'password' => 'p',
            'from_address' => 'me@example.test', 'from_name' => 'Me',
        ]);
    }

    public function test_send_creates_delivery_and_dispatches_job(): void
    {
        Queue::fake();
        $this->withMailConfig();
        $invoice = $this->makeInvoice();

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
            ->callAction('send', data: [
                'to' => ['a@acme.test'],
                'subject' => 'Invoice INV-1',
                'body' => '<p>Hi</p>',
                'signature' => '<p>Me</p>',
                'attach_invoice_pdf' => true,
                'attach_timesheet' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $invoice->fresh()->deliveries()->count());
        Queue::assertPushed(SendInvoiceDelivery::class);
    }

    public function test_send_action_hidden_without_mail_config(): void
    {
        $invoice = $this->makeInvoice();

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
            ->assertActionHidden('send');
    }
}
