<?php

namespace Tests\Feature\Ai\Context;

use App\Models\Client;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Ai\Context\WorkSessionsContextSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkSessionsContextSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_without_client_or_project(): void
    {
        $this->assertNull((new WorkSessionsContextSection)->build());
    }

    public function test_it_lists_sessions_from_the_last_14_days_and_ignores_older_ones(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        WorkSession::create([
            'title' => 'Sessão recente', 'client_id' => $client->id, 'company_id' => $companyId, 'user_id' => $user->id,
            'start' => now()->subDays(2), 'end' => now()->subDays(2)->addHour(),
        ]);

        WorkSession::create([
            'title' => 'Sessão antiga', 'client_id' => $client->id, 'company_id' => $companyId, 'user_id' => $user->id,
            'start' => now()->subDays(30), 'end' => now()->subDays(30)->addHour(),
        ]);

        $context = (new WorkSessionsContextSection)->build($client);

        $this->assertNotNull($context);
        $this->assertStringContainsString('Sessões de trabalho', $context);
        $this->assertStringContainsString('Sessão recente', $context);
        $this->assertStringNotContainsString('Sessão antiga', $context);
    }

    public function test_it_returns_null_when_no_recent_sessions_exist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = Client::create(['company_id' => $user->company->id, 'name' => 'Sem sessões']);

        $this->assertNull((new WorkSessionsContextSection)->build($client));
    }
}
