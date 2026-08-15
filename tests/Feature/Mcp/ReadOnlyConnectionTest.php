<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Exceptions\ReadOnlyViolationException;
use App\Mcp\ReadOnlyDatabaseGuard;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadOnlyConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_armed_connection_blocks_a_write_but_allows_a_read(): void
    {
        $user = User::factory()->create();
        $companyId = (int) $user->company->id;

        $client = Client::create([
            'company_id' => $companyId,
            'name' => 'Cliente para leitura',
        ]);

        $guard = ReadOnlyDatabaseGuard::armOn(DB::connection());

        try {
            $this->assertSame('Cliente para leitura', Client::query()->whereKey($client->id)->value('name'));

            $this->expectException(ReadOnlyViolationException::class);

            Client::create([
                'company_id' => $companyId,
                'name' => 'Não pode existir',
            ]);
        } finally {
            $guard->disable();
        }
    }

    public function test_the_guard_is_released_after_the_mcp_request(): void
    {
        $user = User::factory()->create();

        $guard = ReadOnlyDatabaseGuard::armOn(DB::connection());
        $guard->disable();

        $client = Client::create([
            'company_id' => $user->company->id,
            'name' => 'Escrita normal volta a funcionar',
        ]);

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }
}
