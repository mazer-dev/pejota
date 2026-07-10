<?php

namespace Tests\Feature\Tenancy;

use App\Filament\App\Resources\ClientResource;
use App\Http\Middleware\ApplyTenantToLandlord;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NunoMazer\Samehouse\Facades\Landlord;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TenancyBridgeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Company, 2: Company} */
    private function twoCompaniesWithClients(): array
    {
        $user = User::factory()->create();
        $a = $user->companies()->wherePivot('role', 'owner')->firstOrFail();
        $b = Company::create(['name' => 'B', 'email' => 'b@x.com', 'user_id' => $user->id]);
        $user->companies()->attach($b->id, ['role' => 'owner', 'joined_at' => now()]);

        // Seed under each tenant so samehouse fills company_id on each client.
        Landlord::addTenant('company_id', $a->id);
        Client::create(['name' => 'ClientA']);
        Landlord::removeTenant('company_id');
        Landlord::addTenant('company_id', $b->id);
        Client::create(['name' => 'ClientB']);
        Landlord::removeTenant('company_id');

        return [$user, $a, $b];
    }

    /** Mirror the panel's tenantMiddleware: clear, then apply the Filament tenant to samehouse. */
    private function applyBridge(): void
    {
        foreach (Landlord::getTenants()->keys() as $key) {
            Landlord::removeTenant($key);
        }
        app(ApplyTenantToLandlord::class)->handle(request(), fn ($r) => new Response);
    }

    public function test_bridge_scopes_queries_to_the_active_tenant(): void
    {
        [$user, $a] = $this->twoCompaniesWithClients();
        $this->actingAs($user);
        Filament::setTenant($a);
        $this->applyBridge();

        $this->assertSame(['ClientA'], Client::pluck('name')->all());
    }

    public function test_switching_tenant_rescopes_queries(): void
    {
        [$user, , $b] = $this->twoCompaniesWithClients();
        $this->actingAs($user);
        Filament::setTenant($b);
        $this->applyBridge();

        $this->assertSame(['ClientB'], Client::pluck('name')->all());
    }

    public function test_bridge_aborts_when_no_active_tenant(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant(null);

        try {
            app(ApplyTenantToLandlord::class)->handle(request(), fn ($r) => null);
            $this->fail('Expected an HTTP exception when no tenant is active.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_filament_own_scoping_is_disabled(): void
    {
        $this->assertFalse(ClientResource::isScopedToTenant());
    }
}
