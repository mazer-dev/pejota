<?php

namespace Tests\Feature\Tenancy;

use App\Enums\CompanyRoleEnum;
use App\Http\Middleware\ApplyTenantToLandlord;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NunoMazer\Samehouse\Facades\Landlord;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SpatieTeamBridgeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Company, 2: Company} */
    private function twoCompanies(): array
    {
        $user = User::factory()->create();
        $a = $user->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $b = Company::create(['name' => 'B', 'email' => 'b@x.com', 'user_id' => $user->id]);
        $user->companies()->attach($b->id, ['joined_at' => now()]);

        // Give a role only in A → the differential that proves re-scoping.
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($a->id);
        $user->assignRole(CompanyRoleEnum::Admin->value);

        return [$user, $a, $b];
    }

    private function applyBridge(): void
    {
        foreach (Landlord::getTenants()->keys() as $key) {
            Landlord::removeTenant($key);
        }
        app(ApplyTenantToLandlord::class)->handle(request(), fn ($r) => new Response);
    }

    public function test_bridge_sets_the_spatie_team_to_the_active_tenant(): void
    {
        [$user, $a] = $this->twoCompanies();
        $this->actingAs($user);
        Filament::setTenant($a);

        // twoCompanies() left the registrar's team pointed at $a; clear it so the
        // assertion below can only pass if the bridge itself sets the team from the
        // active tenant, not from setup residue.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $this->applyBridge();

        $this->assertSame($a->id, app(PermissionRegistrar::class)->getPermissionsTeamId());
        $this->assertTrue($user->fresh()->hasRole(CompanyRoleEnum::Admin->value));
    }

    public function test_switching_tenant_rescopes_the_spatie_team(): void
    {
        [$user, , $b] = $this->twoCompanies();
        $this->actingAs($user);
        Filament::setTenant($b);
        $this->applyBridge();

        $this->assertSame($b->id, app(PermissionRegistrar::class)->getPermissionsTeamId());
        // admin was granted only in A → not visible under B's team.
        $this->assertFalse($user->fresh()->hasRole(CompanyRoleEnum::Admin->value));
    }
}
