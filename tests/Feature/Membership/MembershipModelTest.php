<?php

namespace Tests\Feature\Membership;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_belongs_to_many_companies_as_member(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'email' => 'acme@x.com', 'user_id' => $user->id]);

        $user->companies()->attach($company->id, ['joined_at' => now()]);

        $this->assertTrue($user->companies->contains($company));
        $this->assertTrue($company->hasMember($user));
    }
}
