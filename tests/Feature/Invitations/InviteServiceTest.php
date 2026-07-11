<?php

namespace Tests\Feature\Invitations;

use App\Enums\CompanyRoleEnum;
use App\Exceptions\InvitationException;
use App\Mail\InvitationMailable;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InviteServiceTest extends TestCase
{
    use RefreshDatabase;

    private function ownerCompany(): array
    {
        $owner = User::factory()->create();

        return [$owner, $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail()];
    }

    public function test_invite_creates_a_pending_invitation_and_sends_email(): void
    {
        Mail::fake();
        [$owner, $company] = $this->ownerCompany();

        $invitation = app(InvitationService::class)->invite($company, 'invitee@x.com', CompanyRoleEnum::Admin, $owner);

        $this->assertSame($company->id, $invitation->company_id);
        $this->assertSame(CompanyRoleEnum::Admin, $invitation->role);
        $this->assertSame($owner->id, $invitation->invited_by);
        $this->assertTrue($invitation->isPending());
        $this->assertNotEmpty($invitation->token);

        Mail::assertSent(InvitationMailable::class, fn (InvitationMailable $m): bool => $m->hasTo('invitee@x.com'));
    }

    public function test_reinviting_the_same_email_refreshes_the_pending_invitation(): void
    {
        Mail::fake();
        [$owner, $company] = $this->ownerCompany();

        $first = app(InvitationService::class)->invite($company, 'invitee@x.com', CompanyRoleEnum::Member, $owner);
        $second = app(InvitationService::class)->invite($company, 'invitee@x.com', CompanyRoleEnum::Admin, $owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(CompanyRoleEnum::Admin, $second->fresh()->role);
        $this->assertSame(1, Invitation::where('company_id', $company->id)->where('email', 'invitee@x.com')->count());
    }

    public function test_cannot_invite_an_existing_member(): void
    {
        Mail::fake();
        [$owner, $company] = $this->ownerCompany();

        $this->expectException(InvitationException::class);
        app(InvitationService::class)->invite($company, $owner->email, CompanyRoleEnum::Member, $owner);
    }

    public function test_user_email_is_normalized_to_lowercase(): void
    {
        $user = User::factory()->create(['email' => '  MiXeD@Example.COM ']);

        $this->assertSame('mixed@example.com', $user->fresh()->email);
    }

    public function test_invite_detects_an_existing_member_regardless_of_email_case(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        $this->expectException(InvitationException::class);
        app(InvitationService::class)->invite($company, strtoupper($owner->email), CompanyRoleEnum::Member, $owner);
    }
}
