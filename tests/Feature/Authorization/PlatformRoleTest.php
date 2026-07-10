<?php

namespace Tests\Feature\Authorization;

use App\Enums\PlatformRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PlatformRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_platform_role_command_assigns_global_role(): void
    {
        $user = User::factory()->create();

        $this->artisan('pj:grant-platform-role', [
            'email' => $user->email,
            'role' => PlatformRoleEnum::SuperAdmin->value,
        ])->assertSuccessful();

        app(PermissionRegistrar::class)->setPermissionsTeamId(PlatformRoleEnum::TeamId);
        $this->assertTrue($user->fresh()->hasRole(PlatformRoleEnum::SuperAdmin->value));
    }

    public function test_platform_role_does_not_leak_into_a_company_team(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->firstOrFail();

        $this->artisan('pj:grant-platform-role', [
            'email' => $user->email,
            'role' => PlatformRoleEnum::SuperAdmin->value,
        ])->assertSuccessful();

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $this->assertFalse($user->fresh()->hasRole(PlatformRoleEnum::SuperAdmin->value));
    }

    public function test_install_grants_super_admin_to_created_user(): void
    {
        $this->artisan('pj:install')
            ->expectsQuestion('Please enter the user name, enter for "Admin"', 'Root')
            ->expectsQuestion('Please enter the user email or enter for admin@admin.com', 'root@example.com')
            ->expectsQuestion('Please enter the user password or enter for "123456"', 'secret123')
            ->expectsQuestion('Please enter the company name, enter for "My Company"', 'Root Co')
            ->expectsQuestion('Please enter the company email or enter for empty', 'root@co.com')
            ->assertSuccessful();

        $user = User::where('email', 'root@example.com')->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId(PlatformRoleEnum::TeamId);
        $this->assertTrue($user->hasRole(PlatformRoleEnum::SuperAdmin->value));
    }
}
