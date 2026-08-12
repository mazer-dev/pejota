<?php

namespace Tests\Feature\Sending;

use App\Enums\DeliveryStatusEnum;
use App\Enums\InvoiceStatusEnum;
use App\Jobs\SendInvoiceDelivery;
use App\Mail\InvoiceDeliveryMailable;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Invoicing\InvoiceDeliveryComposer;
use App\Services\Invoicing\SendInvoiceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use NunoMazer\Samehouse\Facades\Landlord;
use RuntimeException;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class SendInvoiceDeliveryJobTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
        $this->company->mailConfig()->create([
            'host' => 'smtp.example.test', 'port' => 587, 'username' => 'u', 'password' => 'p',
            'from_address' => 'me@example.test', 'from_name' => 'Me',
        ]);
    }

    private function makeDelivery(string $invoiceStatus): InvoiceDelivery
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id]);
        $invoice = Invoice::create([
            'number' => 'INV-1', 'title' => 'X', 'client_id' => $client->id,
            'company_id' => $this->company->id, 'total' => 100, 'currency' => 'USD', 'status' => $invoiceStatus,
        ]);

        return $invoice->deliveries()->create([
            'created_by' => $this->user->id, 'channel' => 'email', 'status' => 'queued',
            'to' => ['a@acme.test'], 'subject' => 'Hi', 'body' => '<p>b</p>', 'signature' => '<p>s</p>',
            'attach_invoice_pdf' => true,
        ]);
    }

    public function test_success_marks_sent_transitions_draft_and_notifies(): void
    {
        Mail::fake();
        $delivery = $this->makeDelivery('draft');

        (new SendInvoiceDelivery($delivery->id))->handle(app(SendInvoiceService::class));

        $delivery = $delivery->fresh();
        $this->assertSame(DeliveryStatusEnum::Sent, $delivery->status);
        $this->assertNotNull($delivery->sent_at);
        $this->assertSame(InvoiceStatusEnum::SENT, $delivery->invoice->status);
        $this->assertSame(1, $this->user->fresh()->notifications()->count());
    }

    public function test_success_does_not_downgrade_paid_invoice(): void
    {
        Mail::fake();
        $delivery = $this->makeDelivery('paid');

        (new SendInvoiceDelivery($delivery->id))->handle(app(SendInvoiceService::class));

        $this->assertSame(InvoiceStatusEnum::PAID, $delivery->fresh()->invoice->status);
    }

    public function test_success_cleans_up_external_file(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::disk('local')->put('invoice-deliveries/x.pdf', 'BYTES');
        $delivery = $this->makeDelivery('draft');
        $delivery->update(['external_file_path' => 'invoice-deliveries/x.pdf']);

        (new SendInvoiceDelivery($delivery->id))->handle(app(SendInvoiceService::class));

        Storage::disk('local')->assertMissing('invoice-deliveries/x.pdf');
    }

    public function test_failed_marks_failed_records_error_and_notifies(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('invoice-deliveries/x.pdf', 'BYTES');
        $delivery = $this->makeDelivery('draft');
        $delivery->update(['external_file_path' => 'invoice-deliveries/x.pdf']);

        (new SendInvoiceDelivery($delivery->id))->failed(new RuntimeException('smtp down'));

        $delivery = $delivery->fresh();
        $this->assertSame(DeliveryStatusEnum::Failed, $delivery->status);
        $this->assertStringContainsString('smtp down', (string) $delivery->error);
        $this->assertSame(1, $this->user->fresh()->notifications()->count());
        Storage::disk('local')->assertMissing('invoice-deliveries/x.pdf');
    }

    public function test_already_sent_delivery_is_not_resent(): void
    {
        Mail::fake();
        $delivery = $this->makeDelivery('draft');
        $delivery->update(['status' => 'sent']);

        (new SendInvoiceDelivery($delivery->id))->handle(app(SendInvoiceService::class));

        Mail::assertNothingSent();
    }

    /**
     * `Landlord` é SINGLETON e o job escreve nele. Num worker de fila
     * persistente o processo sobrevive entre jobs, então um tenant deixado
     * para trás é herdado pelo job seguinte — de outra empresa. Afeta qualquer
     * model com `BelongsToTenants`, não só fatura.
     *
     * O worker não tem tenant nenhum entre um job e o seguinte. Reproduzir isso
     * exige DUAS coisas, e a segunda foi medida: tirar o tenant do singleton E
     * limpar os models bootados. `actingInCompany()` chamou
     * `applyTenantScopesToDeferredModels()`, que grava o global scope na CLASSE
     * do model; sem limpar, o scope sobrevive e a query seguinte estoura
     * `TenantColumnUnknownException` — estado que um worker nunca tem, porque lá
     * os models bootam com o Landlord vazio e ficam deferidos, sem scope.
     */
    public function test_the_job_leaves_no_tenant_behind_when_it_found_none(): void
    {
        Mail::fake();
        $delivery = $this->makeDelivery('draft');
        Landlord::removeTenant('company_id');
        Model::clearBootedModels();

        (new SendInvoiceDelivery($delivery->id))->handle(app(SendInvoiceService::class));

        $this->assertFalse(
            Landlord::hasTenant('company_id'),
            'O job deixou o tenant da própria empresa no singleton do Landlord: o próximo job do mesmo worker herda esse tenant.'
        );
    }

    /**
     * O caminho de exceção é o pior dos dois: `$tries = 3`, então um SMTP fora
     * do ar deixa o tenant para trás e o worker segue com ele — e `failed()`
     * também não restaura.
     *
     * O fixture é o do worker (sem tenant anterior) POR NECESSIDADE, e não por
     * simetria com o teste acima: quando existe tenant anterior, ele é sempre
     * igual ao da própria entrega — `findOrFail()` é escopado, então o job não
     * alcança entrega de outra empresa. Com os dois iguais, "restaurou" e "não
     * restaurou" produzem o mesmo estado e a assertion passa nos dois mundos.
     */
    public function test_the_job_restores_the_tenant_when_the_send_throws(): void
    {
        $delivery = $this->makeDelivery('draft');
        Landlord::removeTenant('company_id');
        Model::clearBootedModels();

        $service = Mockery::mock(SendInvoiceService::class);
        $service->shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));

        try {
            (new SendInvoiceDelivery($delivery->id))->handle($service);
            $this->fail('O job deveria ter propagado a exceção para a fila poder retentar.');
        } catch (RuntimeException) {
            // esperado: o job não engole a falha, quem retenta é a fila
        }

        $this->assertFalse(
            Landlord::hasTenant('company_id'),
            'O job estourou e deixou o tenant para trás: com $tries = 3, uma falha de SMTP contamina o job seguinte do worker.'
        );
    }

    public function test_end_to_end_send_with_timesheet_attachment(): void
    {
        Mail::fake();

        $client = Client::create(['name' => 'Acme', 'company_id' => $this->company->id, 'currency' => 'USD']);
        WorkSession::create([
            'title' => 'Work', 'company_id' => $this->company->id, 'client_id' => $client->id,
            'is_running' => false, 'rate' => 100.00, 'start' => '2026-05-10 09:00:00', 'end' => '2026-05-10 11:00:00',
        ]);
        $invoice = Invoice::create([
            'number' => 'INV-9', 'title' => 'X', 'client_id' => $client->id,
            'company_id' => $this->company->id, 'total' => 100, 'currency' => 'USD',
            'due_date' => '2026-05-15', 'status' => 'draft',
        ]);

        $delivery = app(InvoiceDeliveryComposer::class)->compose($invoice, [
            'to' => ['a@acme.test'], 'subject' => 'S', 'body' => '<p>b</p>', 'signature' => '<p>s</p>',
            'attach_invoice_pdf' => true, 'attach_timesheet' => true,
            'timesheet_from' => '2026-05-01', 'timesheet_to' => '2026-05-31', 'timesheet_layout' => 'client',
            'external_file_path' => null,
        ], $this->user->id);

        (new SendInvoiceDelivery($delivery->id))->handle(app(SendInvoiceService::class));

        Mail::assertSent(InvoiceDeliveryMailable::class, fn (InvoiceDeliveryMailable $mail): bool => count($mail->files) === 2);
        $this->assertSame(DeliveryStatusEnum::Sent, $delivery->fresh()->status);
    }
}
