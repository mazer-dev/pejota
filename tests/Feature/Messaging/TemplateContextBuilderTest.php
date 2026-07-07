<?php

namespace Tests\Feature\Messaging;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Messaging\TemplateContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class TemplateContextBuilderTest extends TestCase
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

    public function test_builds_expected_tokens_for_invoice(): void
    {
        $client = Client::create(['name' => 'Acme', 'tradename' => 'Acme Co', 'company_id' => $this->user->company->id]);
        $invoice = Invoice::create([
            'number' => 'INV-42',
            'title' => 'Consulting',
            'client_id' => $client->id,
            'company_id' => $this->user->company->id,
            'total' => 1000,
            'currency' => 'USD',
            'due_date' => '2026-05-15',
            'status' => 'draft',
        ]);

        $context = app(TemplateContextBuilder::class)->forInvoice($invoice);

        $this->assertSame('INV-42', $context['invoice.number']);
        $this->assertSame('Consulting', $context['invoice.title']);
        $this->assertSame('USD', $context['invoice.currency']);
        $this->assertSame('Acme', $context['client.name']);
        $this->assertSame('Acme Co', $context['client.tradename']);
        $this->assertSame('May/2026', $context['invoice.due_month']);
        $this->assertArrayHasKey('invoice.total', $context);
        $this->assertNotSame('', $context['invoice.total']);
        $this->assertArrayHasKey('company.name', $context);
        $this->assertSame((string) $invoice->company->name, $context['company.name']);
        $this->assertSame($this->user->name, $context['user.name']);
    }

    public function test_null_due_date_yields_empty_strings(): void
    {
        $client = Client::create(['name' => 'Acme', 'company_id' => $this->user->company->id]);
        $invoice = Invoice::create([
            'number' => 'INV-43',
            'title' => 'X',
            'client_id' => $client->id,
            'company_id' => $this->user->company->id,
            'total' => 0,
            'currency' => 'USD',
            'due_date' => null,
            'status' => 'draft',
        ]);

        $context = app(TemplateContextBuilder::class)->forInvoice($invoice);

        $this->assertSame('', $context['invoice.due_date']);
        $this->assertSame('', $context['invoice.due_month']);
    }
}
