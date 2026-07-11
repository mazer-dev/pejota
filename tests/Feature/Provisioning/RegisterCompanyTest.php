<?php

namespace Tests\Feature\Provisioning;

use App\Enums\CompanyRoleEnum;
use App\Filament\App\Pages\Tenancy\RegisterCompany;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegisterCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_registration_is_enabled_on_the_panel(): void
    {
        $this->assertTrue(Filament::getPanel('app')->hasTenantRegistration());
    }

    public function test_logged_in_user_creates_a_new_company_and_becomes_owner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));

        Livewire::test(RegisterCompany::class)
            ->fillForm(['name' => 'New Venture', 'email' => 'new@venture.com'])
            ->call('register')
            ->assertHasNoFormErrors();

        $company = Company::where('name', 'New Venture')->firstOrFail();
        $this->assertSame('new@venture.com', $company->email);
        $this->assertSame($user->id, $company->user_id);
        $this->assertTrue($company->hasMember($user));

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $this->assertTrue($user->fresh()->hasRole(CompanyRoleEnum::Owner->value));
    }
}
