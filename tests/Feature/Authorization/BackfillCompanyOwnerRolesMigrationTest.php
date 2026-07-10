<?php

namespace Tests\Feature\Authorization;

use App\Enums\CompanyRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BackfillCompanyOwnerRolesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runBackfillMigration(): void
    {
        $path = glob(database_path('migrations/*_backfill_company_owner_roles.php'));
        $migration = require $path[0];
        $migration->up();
    }

    public function test_assigns_owner_role_to_legacy_company_owner(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->firstOrFail();

        // Simulate legacy: strip the spatie owner role that CompanyService now assigns.
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($company->id);
        $user->removeRole(CompanyRoleEnum::Owner->value);
        $this->assertFalse($user->fresh()->hasRole(CompanyRoleEnum::Owner->value));

        $this->runBackfillMigration();

        $registrar->setPermissionsTeamId($company->id);
        $this->assertTrue($user->fresh()->hasRole(CompanyRoleEnum::Owner->value));
    }

    public function test_is_idempotent(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->firstOrFail();

        $this->runBackfillMigration();
        $this->runBackfillMigration();

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $this->assertSame(
            1,
            DB::table('model_has_roles')
                ->where('model_id', $user->id)
                ->where('team_id', $company->id)
                ->count()
        );
    }
}
