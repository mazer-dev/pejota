<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectInaccessibleTenantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Company}
     */
    private function joinedUserWith(bool $isActive): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'email' => 'acme@x.com', 'user_id' => $user->id, 'is_active' => $isActive]);
        $user->companies()->attach($company->id, ['joined_at' => now()]);

        return [$user, $company];
    }

    public function test_blocked_tenant_redirects_when_resolver_configured(): void
    {
        [$user, $company] = $this->joinedUserWith(isActive: false); // suspenso → canAccessTenant falso

        config(['pejota.blocked_tenant_redirect' => FixedLandingResolver::class]);

        $this->actingAs($user)
            ->get("/app/{$company->id}")
            ->assertRedirect('https://landing.test/'.$company->id);
    }

    public function test_blocked_tenant_returns_404_when_no_resolver(): void
    {
        [$user, $company] = $this->joinedUserWith(isActive: false);

        config(['pejota.blocked_tenant_redirect' => null]); // open default

        $this->actingAs($user)
            ->get("/app/{$company->id}")
            ->assertNotFound();
    }

    public function test_accessible_tenant_is_not_redirected(): void
    {
        [$user, $company] = $this->joinedUserWith(isActive: true);

        config(['pejota.blocked_tenant_redirect' => FixedLandingResolver::class]);

        $this->actingAs($user)
            ->get("/app/{$company->id}")
            ->assertSuccessful();
    }
}

class FixedLandingResolver
{
    public function __invoke(Company $tenant, User $user): ?string
    {
        return 'https://landing.test/'.$tenant->id;
    }
}
