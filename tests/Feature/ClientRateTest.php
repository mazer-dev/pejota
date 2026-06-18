<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_persists_default_hourly_rate_in_cents(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $client = Client::create([
            'name' => 'Rated Client',
            'company_id' => $user->company->id,
            'currency' => 'BRL',
            'default_hourly_rate' => 75.00,
            'billable_default' => false,
        ]);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'default_hourly_rate' => 7500,
            'billable_default' => false,
        ]);
    }
}
