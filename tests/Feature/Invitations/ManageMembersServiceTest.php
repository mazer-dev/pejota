<?php

namespace Tests\Feature\Invitations;

use App\Enums\CompanyRoleEnum;
use App\Exceptions\InvitationException;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ManageMembersServiceTest extends TestCase
{
    use RefreshDatabase;

    private function hasRoleInCompany(User $user, Company $company, CompanyRoleEnum $role): bool
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $user->unsetRelation('roles');

        return $user->hasRole($role->value);
    }

    private function memberOf(Company $company, CompanyRoleEnum $role): User
    {
        $user = User::factory()->create();
        $invitation = Invitation::create([
            'company_id' => $company->id, 'email' => $user->email, 'role' => $role,
            'token' => 'tok-'.$user->email, 'expires_at' => now()->addDay(),
        ]);
        app(InvitationService::class)->accept($invitation, $user);

        return $user;
    }

    public function test_change_role_updates_spatie_role(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $member = $this->memberOf($company, CompanyRoleEnum::Member);

        app(InvitationService::class)->changeRole($company, $member, CompanyRoleEnum::Admin, $owner);

        $this->assertTrue($this->hasRoleInCompany($member, $company, CompanyRoleEnum::Admin));
        $this->assertFalse($this->hasRoleInCompany($member, $company, CompanyRoleEnum::Member));
    }

    public function test_remove_member_detaches_and_strips_role(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $member = $this->memberOf($company, CompanyRoleEnum::Member);

        app(InvitationService::class)->removeMember($company, $member, $owner);

        $this->assertFalse($company->hasMember($member));
        $this->assertFalse($this->hasRoleInCompany($member, $company, CompanyRoleEnum::Member));
    }

    public function test_cannot_remove_the_last_owner(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        $this->expectException(InvitationException::class);
        app(InvitationService::class)->removeMember($company, $owner, $owner);
    }

    public function test_cannot_demote_the_last_owner(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        $this->expectException(InvitationException::class);
        app(InvitationService::class)->changeRole($company, $owner, CompanyRoleEnum::Admin, $owner);
    }

    public function test_can_demote_an_owner_when_another_owner_exists(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $secondOwner = $this->memberOf($company, CompanyRoleEnum::Owner);

        app(InvitationService::class)->changeRole($company, $owner, CompanyRoleEnum::Admin, $owner);

        $this->assertTrue($this->hasRoleInCompany($owner, $company, CompanyRoleEnum::Admin));
        $this->assertTrue($this->hasRoleInCompany($secondOwner, $company, CompanyRoleEnum::Owner));
    }

    public function test_admin_cannot_promote_a_member_to_owner(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $admin = $this->memberOf($company, CompanyRoleEnum::Admin);
        $member = $this->memberOf($company, CompanyRoleEnum::Member);

        $this->expectException(InvitationException::class);
        app(InvitationService::class)->changeRole($company, $member, CompanyRoleEnum::Owner, $admin);
    }

    public function test_admin_cannot_change_an_owners_role(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        // Two owners so the last-owner guard cannot fire — isolates the ownerOnly guard.
        $secondOwner = $this->memberOf($company, CompanyRoleEnum::Owner);
        $admin = $this->memberOf($company, CompanyRoleEnum::Admin);

        $this->expectException(InvitationException::class);
        app(InvitationService::class)->changeRole($company, $secondOwner, CompanyRoleEnum::Member, $admin);
    }

    public function test_owner_can_promote_a_member_to_owner(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $member = $this->memberOf($company, CompanyRoleEnum::Member);

        app(InvitationService::class)->changeRole($company, $member, CompanyRoleEnum::Owner, $owner);

        $this->assertTrue($this->hasRoleInCompany($member, $company, CompanyRoleEnum::Owner));
    }

    public function test_admin_cannot_remove_an_owner(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $secondOwner = $this->memberOf($company, CompanyRoleEnum::Owner);
        $admin = $this->memberOf($company, CompanyRoleEnum::Admin);

        $this->expectException(InvitationException::class);
        app(InvitationService::class)->removeMember($company, $secondOwner, $admin);
    }

    public function test_owner_can_remove_another_owner(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $secondOwner = $this->memberOf($company, CompanyRoleEnum::Owner);

        app(InvitationService::class)->removeMember($company, $secondOwner, $owner);

        $this->assertFalse($company->hasMember($secondOwner));
    }
}
