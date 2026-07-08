<?php

namespace Tests\Feature\Ai;

use App\Models\Client;
use App\Models\ClientAiAnalysis;
use App\Models\User;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\ClientAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ClientAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prompts_with_structured_sections_and_persists_a_new_row_without_overwriting(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create([
            'company_id' => $companyId,
            'name' => 'Vivianne',
            'ai_context' => 'Veio da 99freelas.',
        ]);

        $previous = ClientAiAnalysis::create([
            'company_id' => $companyId,
            'client_id' => $client->id,
            'content' => 'Análise anterior.',
        ]);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt): bool {
                return str_contains($prompt, '## Temperatura da relação')
                    && str_contains($prompt, '## Estilo de comunicação do cliente')
                    && str_contains($prompt, '## Compromissos em aberto')
                    && str_contains($prompt, '## Riscos')
                    && str_contains($prompt, '## Próximos passos recomendados')
                    && str_contains($prompt, '<<<DADOS>>>')
                    && str_contains($prompt, '<<<FIM_DADOS>>>')
                    && str_contains($prompt, 'Veio da 99freelas.');
            }))
            ->andReturn("## Temperatura da relação\nMorna.\n\n## Estilo de comunicação do cliente\nObjetivo.\n\n## Compromissos em aberto\nNenhum.\n\n## Riscos\nNenhum.\n\n## Próximos passos recomendados\nAguardar.");

        $this->instance(AiCliRunner::class, $runner);

        $analysis = app(ClientAnalysisService::class)->generate($client);

        $this->assertNotSame($previous->id, $analysis->id);
        $this->assertSame($client->id, $analysis->client_id);
        $this->assertSame($companyId, $analysis->company_id);
        $this->assertStringContainsString('Temperatura da relação', $analysis->content);

        $this->assertDatabaseCount('client_ai_analyses', 2);
    }
}
