<?php

namespace Tests\Feature\Membership;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyCreationMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_becomes_owner_member_of_their_company(): void
    {
        $user = User::factory()->create();

        $company = $user->companies()->wherePivot('role', 'owner')->first();

        $this->assertNotNull($company);
        $this->assertNotNull($company->pivot->joined_at);
        $this->assertSame($user->id, $company->user_id);
    }
}
