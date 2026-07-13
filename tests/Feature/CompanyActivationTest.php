<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_is_active_by_default_with_no_suspension(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'email' => 'a@x.com', 'user_id' => $user->id]);

        $company->refresh();

        $this->assertTrue($company->is_active);
        $this->assertNull($company->suspended_at);
    }

    public function test_inactive_company_denies_tenant_access_even_for_joined_member(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'email' => 'a@x.com', 'user_id' => $user->id]);
        $user->companies()->attach($company->id, ['joined_at' => now()]);

        $this->assertTrue($user->canAccessTenant($company));

        $company->update(['is_active' => false, 'suspended_at' => now()]);

        $this->assertFalse($user->canAccessTenant($company));
    }
}
