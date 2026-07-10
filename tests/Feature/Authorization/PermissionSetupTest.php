<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_teams_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('roles', 'team_id'));
        $this->assertTrue(Schema::hasColumn('model_has_roles', 'team_id'));
    }

    public function test_user_can_receive_a_role(): void
    {
        $role = Role::findOrCreate('tester', 'web');
        $user = User::factory()->create();

        // A non-null team id: null is invalid in the pivot's composite PK on
        // MySQL/MariaDB (a PK column cannot be null; SQLite tolerates it, which
        // hides the bug). 0 is the reserved platform sentinel; any non-null team
        // proves the trait round-trips.
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);
        $user->assignRole($role);

        $this->assertTrue($user->fresh()->hasRole('tester'));
    }
}
