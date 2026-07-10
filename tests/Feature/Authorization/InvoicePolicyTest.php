<?php

namespace Tests\Feature\Authorization;

use App\Enums\CompanyRoleEnum;
use App\Filament\App\Resources\InvoiceResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class InvoicePolicyTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    public function test_owner_can_access_invoice_resource(): void
    {
        $user = User::factory()->create(); // owner of their company
        $this->actingInCompany($user);

        $this->assertTrue(InvoiceResource::canViewAny());
        $this->get(InvoiceResource::getUrl('index'))->assertOk();
    }

    public function test_admin_can_access_invoice_resource(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->firstOrFail();

        $admin = User::factory()->create(); // has their own company; here we add them to $company
        $admin->companies()->attach($company->id, ['joined_at' => now()]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $admin->assignRole(CompanyRoleEnum::Admin->value);

        $this->actingInCompany($admin, $company);

        $this->assertTrue(InvoiceResource::canViewAny());
    }

    public function test_member_cannot_access_invoice_resource(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->firstOrFail();

        $member = User::factory()->create();
        $member->companies()->attach($company->id, ['joined_at' => now()]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $member->assignRole(CompanyRoleEnum::Member->value);

        $this->actingInCompany($member, $company);

        $this->assertFalse(InvoiceResource::canViewAny());
        $this->get(InvoiceResource::getUrl('index'))->assertForbidden();
    }

    public function test_user_without_role_is_barred(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->firstOrFail();

        $stranger = User::factory()->create();
        $stranger->companies()->attach($company->id, ['joined_at' => now()]); // joined, no role

        $this->actingInCompany($stranger, $company);

        $this->assertFalse(InvoiceResource::canViewAny());
        $this->get(InvoiceResource::getUrl('index'))->assertForbidden();
    }
}
