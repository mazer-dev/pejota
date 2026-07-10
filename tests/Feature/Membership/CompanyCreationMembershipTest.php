<?php

namespace Tests\Feature\Membership;

use App\Enums\CompanyRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompanyCreationMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_becomes_owner_of_their_company(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $this->assertNotNull($company->pivot->joined_at);
        $this->assertSame($user->id, $company->user_id);
        $this->assertTrue($user->fresh()->hasRole(CompanyRoleEnum::Owner->value));
    }
}
