<?php

namespace Tests\Feature\Sending;

use App\Mail\InvoiceDeliveryMailable;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoicing\SendInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class SendInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Landlord::addTenant('company_id', $this->user->company->id);

        $this->user->company->mailConfig()->create([
            'host' => 'smtp.example.test', 'port' => 587, 'username' => 'u', 'password' => 'p',
            'from_address' => 'me@example.test', 'from_name' => 'Me',
        ]);
    }

    private function makeInvoice(): Invoice
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->user->company->id]);

        return Invoice::create([
            'number' => 'INV-1', 'title' => 'Consulting', 'client_id' => $client->id,
            'company_id' => $this->user->company->id, 'total' => 100, 'currency' => 'USD', 'status' => 'draft',
        ]);
    }

    public function test_sends_mailable_via_company_mailer_with_invoice_pdf(): void
    {
        Mail::fake();
        $invoice = $this->makeInvoice();
        $delivery = $invoice->deliveries()->create([
            'created_by' => $this->user->id, 'channel' => 'email', 'status' => 'queued',
            'to' => ['a@acme.test'], 'subject' => 'Hi', 'body' => '<p>b</p>', 'signature' => '<p>s</p>',
            'attach_invoice_pdf' => true,
        ]);

        app(SendInvoiceService::class)->send($delivery);

        Mail::assertSent(InvoiceDeliveryMailable::class, function (InvoiceDeliveryMailable $mail): bool {
            return $mail->hasTo('a@acme.test') && count($mail->files) === 1;
        });
    }

    public function test_attaches_external_file_when_present(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::disk('local')->put('invoice-deliveries/x.pdf', 'PDFBYTES');

        $invoice = $this->makeInvoice();
        $delivery = $invoice->deliveries()->create([
            'created_by' => $this->user->id, 'channel' => 'email', 'status' => 'queued',
            'to' => ['a@acme.test'], 'subject' => 'Hi', 'body' => '<p>b</p>', 'signature' => '<p>s</p>',
            'attach_invoice_pdf' => true, 'external_file_path' => 'invoice-deliveries/x.pdf',
        ]);

        app(SendInvoiceService::class)->send($delivery);

        Mail::assertSent(InvoiceDeliveryMailable::class, fn (InvoiceDeliveryMailable $mail): bool => count($mail->files) === 2);
    }
}
