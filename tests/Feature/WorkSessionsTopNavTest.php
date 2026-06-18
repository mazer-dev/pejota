<?php

namespace Tests\Feature;

use App\Livewire\WorkSessionsTopNav;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkSessionsTopNavTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_lists_running_sessions(): void
    {
        WorkSession::create([
            'title' => 'Running A',
            'company_id' => $this->user->company->id,
            'start' => now(),
            'is_running' => true,
            'rate' => 0,
        ]);

        Livewire::test(WorkSessionsTopNav::class)
            ->assertSee('Running A')
            ->assertSee('1');
    }

    public function test_start_session_creates_running_session_with_resolved_rate(): void
    {
        $client = Client::create([
            'name' => 'Acme',
            'company_id' => $this->user->company->id,
            'currency' => 'BRL',
            'default_hourly_rate' => 60.00,
            'billable_default' => true,
        ]);

        Livewire::test(WorkSessionsTopNav::class)
            ->set('newTitle', 'Quick start')
            ->set('newClient', $client->id)
            ->call('startSession');

        $session = WorkSession::where('title', 'Quick start')->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->is_running);
        $this->assertEquals(60.00, $session->rate);
        $this->assertSame('BRL', $session->currency);
        $this->assertTrue($session->billable);
    }

    public function test_stop_session_finishes_it(): void
    {
        $session = WorkSession::create([
            'title' => 'Stop me',
            'company_id' => $this->user->company->id,
            'start' => now()->subHour(),
            'is_running' => true,
            'rate' => 0,
        ]);

        Livewire::test(WorkSessionsTopNav::class)
            ->call('stopSession', $session->id);

        $session->refresh();
        $this->assertFalse($session->is_running);
        $this->assertNotNull($session->end);
        $this->assertGreaterThanOrEqual(59, $session->duration);
    }
}
