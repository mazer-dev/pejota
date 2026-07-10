<?php

namespace Tests\Feature\Membership;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrateMembershipsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfills_owner_membership_for_legacy_companies(): void
    {
        $user = User::factory()->create();
        // Simula legado: empresa sem pivot.
        $legacy = Company::create(['name' => 'Legacy', 'email' => 'l@x.com', 'user_id' => $user->id]);
        DB::table('company_user')->where('company_id', $legacy->id)->delete();

        $this->artisan('pj:migrate-memberships')->assertSuccessful();

        $this->assertDatabaseHas('company_user', [
            'company_id' => $legacy->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_is_idempotent(): void
    {
        $user = User::factory()->create();
        $legacy = Company::create(['name' => 'Legacy', 'email' => 'leg@x.com', 'user_id' => $user->id]);
        DB::table('company_user')->where('company_id', $legacy->id)->delete();

        $this->artisan('pj:migrate-memberships')->assertSuccessful(); // first run backfills legacy
        $this->artisan('pj:migrate-memberships')->assertSuccessful(); // second run must be a no-op

        $this->assertSame(1, DB::table('company_user')->where('company_id', $legacy->id)->count());
    }
}
