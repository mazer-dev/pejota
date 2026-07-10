<?php

namespace Tests\Feature\Authorization;

use App\Enums\CompanyRoleEnum;
use App\Enums\PlatformRoleEnum;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleDefinitionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_five_global_roles_exist_after_migrate(): void
    {
        $names = [
            CompanyRoleEnum::Owner->value,
            CompanyRoleEnum::Admin->value,
            CompanyRoleEnum::Member->value,
            PlatformRoleEnum::SuperAdmin->value,
            PlatformRoleEnum::SupportTier1->value,
        ];

        foreach ($names as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            $this->assertNotNull($role, "Role {$name} should exist");
            $this->assertNull($role->team_id, "Role {$name} should be global (team_id null)");
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->assertSame(1, Role::where('name', CompanyRoleEnum::Owner->value)->count());
    }
}
