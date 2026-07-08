<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateClientAnalysis;
use App\Models\Client;
use App\Models\User;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\ClientAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GenerateClientAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_the_analysis_and_notifies_the_user_on_success(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn("## Temperatura da relação\nBoa.");
        $this->instance(AiCliRunner::class, $runner);

        (new GenerateClientAnalysis($client, $user))->handle(app(ClientAnalysisService::class));

        $this->assertDatabaseCount('client_ai_analyses', 1);
        $this->assertSame(1, DatabaseNotification::where('notifiable_id', $user->id)->count());

        $notification = DatabaseNotification::where('notifiable_id', $user->id)->first();
        $this->assertStringContainsString('is ready', $notification->data['title']);
    }

    public function test_it_notifies_failure_when_the_ai_cli_throws(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andThrow(new RuntimeException('CLI indisponível.'));
        $this->instance(AiCliRunner::class, $runner);

        (new GenerateClientAnalysis($client, $user))->handle(app(ClientAnalysisService::class));

        $this->assertDatabaseCount('client_ai_analyses', 0);

        $notification = DatabaseNotification::where('notifiable_id', $user->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Failed', $notification->data['title']);
    }
}
