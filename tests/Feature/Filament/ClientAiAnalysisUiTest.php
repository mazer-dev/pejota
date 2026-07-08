<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Resources\ClientResource\Pages\ViewClient;
use App\Jobs\GenerateClientAnalysis;
use App\Models\Client;
use App\Models\ClientAiAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class ClientAiAnalysisUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_empty_state_when_client_has_no_analysis(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $client = Client::create(['company_id' => $user->company->id, 'name' => 'Vivianne']);

        Livewire::test(ViewClient::class, ['record' => $client->id])
            ->assertSee('No analysis generated yet.');
    }

    public function test_it_shows_the_latest_analysis_content(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        ClientAiAnalysis::create([
            'company_id' => $companyId, 'client_id' => $client->id, 'content' => 'Temperatura: morna.',
        ]);

        Livewire::test(ViewClient::class, ['record' => $client->id])
            ->assertSee('Temperatura: morna.');
    }

    public function test_generate_analysis_action_dispatches_the_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $client = Client::create(['company_id' => $user->company->id, 'name' => 'Vivianne']);

        Livewire::test(ViewClient::class, ['record' => $client->id])
            ->callInfolistAction('aiAnalysisSection', 'generateAnalysis');

        Bus::assertDispatched(GenerateClientAnalysis::class, fn ($job): bool => $job->client->is($client) && $job->user->is($user));
    }
}
