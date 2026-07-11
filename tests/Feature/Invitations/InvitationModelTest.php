<?php

namespace Tests\Feature\Invitations;

use App\Enums\CompanyRoleEnum;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationModelTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        $user = User::factory()->create();

        return $user->companies()->wherePivotNotNull('joined_at')->firstOrFail();
    }

    public function test_casts_and_relations(): void
    {
        $company = $this->company();

        $invitation = Invitation::create([
            'company_id' => $company->id,
            'email' => 'invitee@x.com',
            'role' => CompanyRoleEnum::Member,
            'token' => 'tok-1',
            'expires_at' => now()->addDays(7),
            'invited_by' => $company->user_id,
        ]);

        $this->assertInstanceOf(CompanyRoleEnum::class, $invitation->role);
        $this->assertTrue($invitation->expires_at->isFuture());
        $this->assertSame($company->id, $invitation->company->id);
        $this->assertSame($company->user_id, $invitation->invitedBy->id);
        $this->assertTrue($company->invitations()->whereKey($invitation->id)->exists());
    }

    public function test_pending_expired_accepted_states(): void
    {
        $company = $this->company();

        $pending = Invitation::create([
            'company_id' => $company->id, 'email' => 'a@x.com', 'role' => CompanyRoleEnum::Member,
            'token' => 't-pending', 'expires_at' => now()->addDay(),
        ]);
        $expired = Invitation::create([
            'company_id' => $company->id, 'email' => 'b@x.com', 'role' => CompanyRoleEnum::Member,
            'token' => 't-expired', 'expires_at' => now()->subDay(),
        ]);
        $accepted = Invitation::create([
            'company_id' => $company->id, 'email' => 'c@x.com', 'role' => CompanyRoleEnum::Member,
            'token' => 't-accepted', 'expires_at' => now()->addDay(), 'accepted_at' => now(),
        ]);

        $this->assertTrue($pending->isPending());
        $this->assertFalse($pending->isExpired());

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($expired->isPending());

        $this->assertTrue($accepted->isAccepted());
        $this->assertFalse($accepted->isPending());
    }
}
