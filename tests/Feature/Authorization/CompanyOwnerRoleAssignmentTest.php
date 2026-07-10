<?php

namespace Tests\Feature\Authorization;

use App\Enums\CompanyRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompanyOwnerRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_creator_gets_owner_role_scoped_to_that_company(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $this->assertTrue($user->fresh()->hasRole(CompanyRoleEnum::Owner->value));
    }

    public function test_owner_role_does_not_leak_to_a_foreign_team(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->firstOrFail();

        // A different, unrelated team id → no role there.
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id + 999);

        $this->assertFalse($user->fresh()->hasRole(CompanyRoleEnum::Owner->value));
    }
}
