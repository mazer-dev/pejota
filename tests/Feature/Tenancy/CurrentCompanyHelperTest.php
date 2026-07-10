<?php

namespace Tests\Feature\Tenancy;

use App\Helpers\PejotaHelper;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentCompanyHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_company_returns_active_tenant(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->first();
        $this->actingAs($user);
        Filament::setTenant($company);

        $this->assertTrue(PejotaHelper::currentCompany()->is($company));
    }
}
