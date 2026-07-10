<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillCompanyMembershipsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runBackfillMigration(): void
    {
        $path = glob(database_path('migrations/*_backfill_company_memberships.php'));

        $migration = require $path[0];
        $migration->up();
    }

    public function test_backfills_owner_membership_for_legacy_companies(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->wherePivot('role', 'owner')->firstOrFail();

        // Simulate a pre-pivot install: the company exists via companies.user_id
        // but has no company_user row yet.
        DB::table('company_user')->where('company_id', $company->id)->delete();

        $this->runBackfillMigration();

        $membership = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame('owner', $membership->role);
        $this->assertNotNull($membership->joined_at);
    }

    public function test_is_idempotent_and_does_not_duplicate_existing_memberships(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->wherePivot('role', 'owner')->firstOrFail();

        $this->runBackfillMigration();

        $count = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->count();

        $this->assertSame(1, $count);
    }
}
