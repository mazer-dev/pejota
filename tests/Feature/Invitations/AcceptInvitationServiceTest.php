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

class AcceptInvitationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function ownerCompany(): array
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        return [$owner, $company];
    }

    private function invitation(Company $company, string $email, CompanyRoleEnum $role = CompanyRoleEnum::Member): Invitation
    {
        return Invitation::create([
            'company_id' => $company->id,
            'email' => $email,
            'role' => $role,
            'token' => 'tok-'.$email,
            'expires_at' => now()->addDay(),
        ]);
    }

    private function hasRoleInCompany(User $user, Company $company, CompanyRoleEnum $role): bool
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $user->unsetRelation('roles');

        return $user->hasRole($role->value);
    }

    public function test_existing_user_is_attached_with_role_and_no_new_company(): void
    {
        [, $company] = $this->ownerCompany();
        $invitee = User::factory()->create();
        $ownCompany = $invitee->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        $invitation = $this->invitation($company, $invitee->email, CompanyRoleEnum::Admin);

        app(InvitationService::class)->accept($invitation, $invitee);

        $this->assertTrue($company->hasMember($invitee));
        $this->assertTrue($this->hasRoleInCompany($invitee, $company, CompanyRoleEnum::Admin));
        $this->assertNotNull($invitation->fresh()->accepted_at);

        // "sem nova assinatura": nenhuma empresa NOVA foi provisionada — segue com a própria + a convidada.
        $this->assertSame(2, $invitee->companies()->wherePivotNotNull('joined_at')->count());

        // re-escopo: na PRÓPRIA empresa ele é owner, NÃO admin.
        $this->assertTrue($this->hasRoleInCompany($invitee, $ownCompany, CompanyRoleEnum::Owner));
        $this->assertFalse($this->hasRoleInCompany($invitee, $ownCompany, CompanyRoleEnum::Admin));
    }

    public function test_email_mismatch_is_rejected(): void
    {
        [, $company] = $this->ownerCompany();
        $invitee = User::factory()->create(['email' => 'real@x.com']);
        $invitation = $this->invitation($company, 'someone-else@x.com');

        $this->expectException(InvitationException::class);
        app(InvitationService::class)->accept($invitation, $invitee);
    }

    public function test_expired_invitation_is_rejected(): void
    {
        [, $company] = $this->ownerCompany();
        $invitee = User::factory()->create();
        $invitation = $this->invitation($company, $invitee->email);
        $invitation->update(['expires_at' => now()->subDay()]);

        $this->expectException(InvitationException::class);
        app(InvitationService::class)->accept($invitation, $invitee);
    }

    public function test_new_user_is_created_without_auto_company(): void
    {
        [, $company] = $this->ownerCompany();
        $invitation = $this->invitation($company, 'newbie@x.com', CompanyRoleEnum::Member);

        $user = app(InvitationService::class)->acceptAsNewUser($invitation, 'Newbie', 'secret-pass-123');

        $this->assertSame('newbie@x.com', $user->email);
        $this->assertTrue($company->hasMember($user));
        $this->assertTrue($this->hasRoleInCompany($user, $company, CompanyRoleEnum::Member));

        // Guard: o convidado NÃO ganhou empresa própria — pertence só à empresa que o convidou.
        $this->assertSame(1, $user->companies()->wherePivotNotNull('joined_at')->count());
        $this->assertSame($company->id, $user->companies()->wherePivotNotNull('joined_at')->first()->id);
    }
}
