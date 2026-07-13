<?php

namespace Tests\Feature;

use App\Enums\PlatformRoleEnum;
use App\Models\User;
use App\PejotaCloud\Providers\PejotaCloudServiceProvider;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use MockeryPHPUnitIntegration, RefreshDatabase;

    private function fakePanel(string $id): Panel
    {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn($id);

        return $panel;
    }

    public function test_app_panel_is_accessible_to_any_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('app')));
    }

    public function test_non_app_panel_requires_a_platform_role(): void
    {
        $user = User::factory()->create();
        $adminPanel = $this->fakePanel('admin');

        $this->assertFalse($user->canAccessPanel($adminPanel));

        $this->artisan('pj:grant-platform-role', [
            'email' => $user->email,
            'role' => PlatformRoleEnum::SuperAdmin->value,
        ])->assertSuccessful();

        $this->assertTrue($user->fresh()->canAccessPanel($adminPanel));
    }

    public function test_has_platform_role_distinguishes_specific_roles(): void
    {
        $user = User::factory()->create();
        $this->artisan('pj:grant-platform-role', [
            'email' => $user->email,
            'role' => PlatformRoleEnum::SupportTier1->value,
        ])->assertSuccessful();

        $user = $user->fresh();

        $this->assertTrue($user->hasPlatformRole());
        $this->assertTrue($user->hasPlatformRole(PlatformRoleEnum::SupportTier1));
        $this->assertFalse($user->hasPlatformRole(PlatformRoleEnum::SuperAdmin));
    }

    public function test_platform_role_check_restores_previous_team_context(): void
    {
        $user = User::factory()->create();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(42);

        $user->hasPlatformRole();

        $this->assertSame(42, $registrar->getPermissionsTeamId());
    }

    public function test_platform_role_check_clears_role_cache_so_it_does_not_leak_into_company_context(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->firstOrFail();

        $this->artisan('pj:grant-platform-role', [
            'email' => $user->email,
            'role' => PlatformRoleEnum::SuperAdmin->value,
        ])->assertSuccessful();

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($company->id);

        // hasPlatformRole reads super-admin at team 0, then its finally restores the
        // company team AND clears the roles cache. No manual unsetRelation() below —
        // this asserts the finally did the cleanup (without it, the cached team-0
        // roles would leak and the assertion would see super-admin in company context).
        $this->assertTrue($user->hasPlatformRole(PlatformRoleEnum::SuperAdmin));

        $this->assertSame($company->id, $registrar->getPermissionsTeamId());
        $this->assertFalse($user->hasRole(PlatformRoleEnum::SuperAdmin->value));
    }

    public function test_admin_panel_is_registered_only_when_the_cloud_overlay_is_present(): void
    {
        $hasCloudOverlay = class_exists(PejotaCloudServiceProvider::class);

        $this->assertSame($hasCloudOverlay, array_key_exists('admin', Filament::getPanels()));
    }
}
