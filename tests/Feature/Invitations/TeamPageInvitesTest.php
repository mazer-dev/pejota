<?php

namespace Tests\Feature\Invitations;

use App\Enums\CompanyRoleEnum;
use App\Filament\App\Pages\Team;
use App\Mail\InvitationMailable;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class TeamPageInvitesTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    public function test_owner_invites_by_email(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        $this->actingInCompany($owner, $company);

        Livewire::test(Team::class)
            ->callAction('invite', ['email' => 'invitee@x.com', 'role' => CompanyRoleEnum::Member->value]);

        $this->assertDatabaseHas('invitations', [
            'company_id' => $company->id,
            'email' => 'invitee@x.com',
            'role' => CompanyRoleEnum::Member->value,
        ]);
        Mail::assertSent(InvitationMailable::class);
    }

    public function test_owner_revokes_a_pending_invitation(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $invitation = Invitation::create([
            'company_id' => $company->id, 'email' => 'invitee@x.com', 'role' => CompanyRoleEnum::Member,
            'token' => 'tok-revoke', 'expires_at' => now()->addDay(),
        ]);

        $this->actingInCompany($owner, $company);

        Livewire::test(Team::class)
            ->call('revokeInvitation', $invitation->id);

        $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
    }

    public function test_owner_resends_a_pending_invitation(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $invitation = Invitation::create([
            'company_id' => $company->id, 'email' => 'invitee@x.com', 'role' => CompanyRoleEnum::Member,
            'token' => 'tok-resend', 'expires_at' => now()->addDay(),
        ]);

        $this->actingInCompany($owner, $company);

        Livewire::test(Team::class)
            ->call('resendInvitation', $invitation->id);

        Mail::assertSent(InvitationMailable::class, fn (InvitationMailable $m): bool => $m->hasTo('invitee@x.com'));
    }

    public function test_revoking_a_nonexistent_invitation_does_not_notify_success(): void
    {
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $this->actingInCompany($owner, $company);

        Livewire::test(Team::class)
            ->call('revokeInvitation', 999999)
            ->assertNotNotified(__('Invitation revoked'));
    }

    public function test_cannot_revoke_or_resend_another_companys_invitation(): void
    {
        Mail::fake();
        $ownerA = User::factory()->create();
        $companyA = $ownerA->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $ownerB = User::factory()->create();
        $companyB = $ownerB->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $invB = Invitation::create([
            'company_id' => $companyB->id, 'email' => 'b-invitee@x.com', 'role' => CompanyRoleEnum::Member,
            'token' => 'tok-cross', 'expires_at' => now()->addDay(),
        ]);

        $this->actingInCompany($ownerA, $companyA);

        Livewire::test(Team::class)->call('revokeInvitation', $invB->id);
        Livewire::test(Team::class)->call('resendInvitation', $invB->id);

        $this->assertDatabaseHas('invitations', ['id' => $invB->id]);
        Mail::assertNothingSent();
    }
}
