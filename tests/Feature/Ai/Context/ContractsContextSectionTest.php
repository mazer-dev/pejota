<?php

namespace Tests\Feature\Ai\Context;

use App\Models\Client;
use App\Models\Contract;
use App\Models\User;
use App\Services\Ai\Context\ContractsContextSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractsContextSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_without_client(): void
    {
        $this->assertNull((new ContractsContextSection)->build(null));
    }

    public function test_it_lists_only_currently_active_contracts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        Contract::create([
            'title' => 'Contrato vigente', 'content' => 'x', 'client_id' => $client->id,
            'start_at' => now()->subMonth()->toDateString(), 'end_at' => now()->addMonth()->toDateString(),
            'signatures' => [],
        ]);

        Contract::create([
            'title' => 'Contrato expirado', 'content' => 'x', 'client_id' => $client->id,
            'start_at' => now()->subYear()->toDateString(), 'end_at' => now()->subMonth()->toDateString(),
            'signatures' => [],
        ]);

        Contract::create([
            'title' => 'Contrato indeterminado', 'content' => 'x', 'client_id' => $client->id,
            'start_at' => now()->subMonth()->toDateString(), 'end_at' => null,
            'signatures' => [],
        ]);

        $context = (new ContractsContextSection)->build($client);

        $this->assertNotNull($context);
        $this->assertStringContainsString('Contratos vigentes:', $context);
        $this->assertStringContainsString('Contrato vigente', $context);
        $this->assertStringContainsString('Contrato indeterminado', $context);
        $this->assertStringContainsString('indeterminado', $context);
        $this->assertStringNotContainsString('Contrato expirado', $context);
    }
}
