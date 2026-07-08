<?php

namespace Tests\Feature\Ai\Context;

use App\Models\Client;
use App\Models\ClientAiAnalysis;
use App\Models\User;
use App\Services\Ai\Context\ClientAnalysisContextSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAnalysisContextSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_without_client(): void
    {
        $this->assertNull((new ClientAnalysisContextSection)->build(null));
    }

    public function test_it_returns_null_when_client_has_no_analysis(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = Client::create(['company_id' => $user->company->id, 'name' => 'Sem análise']);

        $this->assertNull((new ClientAnalysisContextSection)->build($client));
    }

    public function test_it_returns_the_latest_analysis_flagged_with_its_age(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        $older = ClientAiAnalysis::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'content' => 'Análise antiga',
        ]);
        $older->forceFill(['created_at' => now()->subDays(10)])->save();

        $newer = ClientAiAnalysis::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'content' => 'Temperatura: morna. Riscos: nenhum.',
        ]);
        $newer->forceFill(['created_at' => now()->subDays(3)])->save();

        $context = (new ClientAnalysisContextSection)->build($client->fresh());

        $this->assertNotNull($context);
        $this->assertStringContainsString('gerada há 3 dia(s)', $context);
        $this->assertStringContainsString('Temperatura: morna. Riscos: nenhum.', $context);
        $this->assertStringNotContainsString('Análise antiga', $context);
    }
}
