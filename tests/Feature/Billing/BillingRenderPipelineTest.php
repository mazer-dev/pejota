<?php

namespace Tests\Feature\Billing;

use App\Enums\CompanySettingsEnum;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Messaging\TemplateContextBuilder;
use App\Services\Messaging\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class BillingRenderPipelineTest extends TestCase
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

    public function test_resolved_body_renders_preserving_html_and_escaping_values(): void
    {
        $this->company->settings()->set(CompanySettingsEnum::BILLING_EMAIL_BODY->value, '<p>Hi {{ client.name }}</p>');

        $client = Client::create(['name' => 'A & B', 'company_id' => $this->company->id]);
        $invoice = Invoice::create([
            'number' => 'INV-1', 'title' => 'X', 'client_id' => $client->id,
            'company_id' => $this->company->id, 'total' => 100, 'currency' => 'USD',
            'due_date' => '2026-05-15', 'status' => 'draft',
        ]);

        $template = $client->resolvedEmailBody();
        $context = app(TemplateContextBuilder::class)->forInvoice($invoice);
        $rendered = app(TemplateRenderer::class)->render($template, $context, html: true);

        $this->assertSame('<p>Hi A &amp; B</p>', $rendered);
    }

    public function test_subject_renders_plain_without_escaping(): void
    {
        $this->company->settings()->set(CompanySettingsEnum::BILLING_EMAIL_SUBJECT->value, 'Invoice {{ invoice.number }} - {{ client.name }}');

        $client = Client::create(['name' => 'A & B', 'company_id' => $this->company->id]);
        $invoice = Invoice::create([
            'number' => 'INV-9', 'title' => 'X', 'client_id' => $client->id,
            'company_id' => $this->company->id, 'total' => 100, 'currency' => 'USD',
            'due_date' => null, 'status' => 'draft',
        ]);

        $template = $client->resolvedEmailSubject();
        $context = app(TemplateContextBuilder::class)->forInvoice($invoice);
        $rendered = app(TemplateRenderer::class)->render($template, $context, html: false);

        $this->assertSame('Invoice INV-9 - A & B', $rendered);
    }
}
