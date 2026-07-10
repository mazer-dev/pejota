<?php

namespace Tests\Feature\Membership;

use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTenantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_tenants_returns_only_joined_companies(): void
    {
        $user = User::factory()->create();
        $joined = Company::create(['name' => 'Joined', 'email' => 'j@x.com', 'user_id' => $user->id]);
        $pending = Company::create(['name' => 'Pending', 'email' => 'p@x.com', 'user_id' => $user->id]);
        $user->companies()->attach($joined->id, ['joined_at' => now()]);
        $user->companies()->attach($pending->id, ['invited_at' => now()]);

        $panel = Filament::getPanel('app');
        $tenants = $user->getTenants($panel);

        $this->assertTrue($tenants->contains($joined));
        $this->assertFalse($tenants->contains($pending));
    }

    public function test_can_access_tenant_requires_joined_membership(): void
    {
        $user = User::factory()->create();
        $member = Company::create(['name' => 'M', 'email' => 'm@x.com', 'user_id' => $user->id]);
        $pending = Company::create(['name' => 'P2', 'email' => 'p2@x.com', 'user_id' => $user->id]);
        $stranger = Company::create(['name' => 'S', 'email' => 's@x.com', 'user_id' => $user->id]);
        $user->companies()->attach($member->id, ['joined_at' => now()]);
        $user->companies()->attach($pending->id, ['invited_at' => now()]);

        $this->assertTrue($user->canAccessTenant($member));
        $this->assertFalse($user->canAccessTenant($pending));
        $this->assertFalse($user->canAccessTenant($stranger));
    }
}
