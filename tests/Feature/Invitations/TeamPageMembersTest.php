<?php

namespace Tests\Feature\Invitations;

use App\Enums\CompanyRoleEnum;
use App\Filament\App\Pages\Team;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class TeamPageMembersTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private function memberOf($company, CompanyRoleEnum $role): User
    {
        $user = User::factory()->create();
        $invitation = Invitation::create([
            'company_id' => $company->id, 'email' => $user->email, 'role' => $role,
            'token' => 'tok-'.$user->email, 'expires_at' => now()->addDay(),
        ]);
        app(InvitationService::class)->accept($invitation, $user);

        return $user;
    }

    private function hasRoleInCompany(User $user, $company, CompanyRoleEnum $role): bool
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $user->unsetRelation('roles');

        return $user->hasRole($role->value);
    }

    public function test_member_cannot_access_the_page(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $member = $this->memberOf($company, CompanyRoleEnum::Member);

        $this->actingInCompany($member, $company);

        $this->get(Team::getUrl(tenant: $company))->assertForbidden();
    }

    public function test_owner_sees_members(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $member = $this->memberOf($company, CompanyRoleEnum::Member);

        $this->actingInCompany($owner, $company);

        Livewire::test(Team::class)
            ->assertCanSeeTableRecords([$owner, $member]);
    }

    public function test_owner_changes_a_member_role(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $member = $this->memberOf($company, CompanyRoleEnum::Member);

        $this->actingInCompany($owner, $company);

        Livewire::test(Team::class)
            ->callTableAction('changeRole', $member, ['role' => CompanyRoleEnum::Admin->value]);

        $this->assertTrue($this->hasRoleInCompany($member, $company, CompanyRoleEnum::Admin));
    }

    public function test_owner_removes_a_member(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $member = $this->memberOf($company, CompanyRoleEnum::Member);

        $this->actingInCompany($owner, $company);

        Livewire::test(Team::class)
            ->callTableAction('remove', $member);

        $this->assertFalse($company->hasMember($member));
    }

    public function test_removing_last_owner_is_blocked_with_notification(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        $this->actingInCompany($owner, $company);

        Livewire::test(Team::class)
            ->callTableAction('remove', $owner);

        $this->assertTrue($company->hasMember($owner));
    }

    public function test_admin_cannot_promote_to_owner_via_page(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $admin = $this->memberOf($company, CompanyRoleEnum::Admin);
        $member = $this->memberOf($company, CompanyRoleEnum::Member);

        $this->actingInCompany($admin, $company);

        Livewire::test(Team::class)
            ->callTableAction('changeRole', $member, ['role' => CompanyRoleEnum::Owner->value]);

        $this->assertFalse($this->hasRoleInCompany($member, $company, CompanyRoleEnum::Owner));
    }
}
