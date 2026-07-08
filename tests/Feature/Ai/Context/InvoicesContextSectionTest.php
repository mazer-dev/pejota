<?php

namespace Tests\Feature\Ai\Context;

use App\Enums\InvoiceStatusEnum;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Ai\Context\InvoicesContextSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicesContextSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_without_client(): void
    {
        $this->assertNull((new InvoicesContextSection)->build(null));
    }

    public function test_it_lists_overdue_open_and_recent_paid_invoices(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        Invoice::create([
            'number' => 'INV-1', 'title' => 'Fatura vencida', 'client_id' => $client->id, 'company_id' => $companyId,
            'due_date' => now()->subDays(5)->toDateString(), 'total' => 1000, 'status' => InvoiceStatusEnum::SENT->value,
        ]);

        Invoice::create([
            'number' => 'INV-2', 'title' => 'Fatura em aberto', 'client_id' => $client->id, 'company_id' => $companyId,
            'due_date' => now()->addDays(5)->toDateString(), 'total' => 500, 'status' => InvoiceStatusEnum::SENT->value,
        ]);

        Invoice::create([
            'number' => 'INV-3', 'title' => 'Fatura paga', 'client_id' => $client->id, 'company_id' => $companyId,
            'due_date' => now()->subDays(20)->toDateString(), 'total' => 800, 'status' => InvoiceStatusEnum::PAID->value,
            'payment_date' => now()->subDays(10)->toDateString(),
        ]);

        $context = (new InvoicesContextSection)->build($client);

        $this->assertNotNull($context);
        $this->assertStringContainsString('Vencidas:', $context);
        $this->assertStringContainsString('INV-1', $context);
        $this->assertStringContainsString('dia(s) de atraso', $context);
        $this->assertStringContainsString('Em aberto:', $context);
        $this->assertStringContainsString('INV-2', $context);
        $this->assertStringContainsString('Últimas pagas:', $context);
        $this->assertStringContainsString('INV-3', $context);
    }

    public function test_it_returns_null_when_client_has_no_invoices(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = Client::create(['company_id' => $user->company->id, 'name' => 'Sem faturas']);

        $this->assertNull((new InvoicesContextSection)->build($client));
    }
}
