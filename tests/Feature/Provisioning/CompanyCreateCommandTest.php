<?php

namespace Tests\Feature\Provisioning;

use App\Enums\CompanyRoleEnum;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompanyCreateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_company_owned_by_the_user(): void
    {
        $user = User::factory()->create();

        $this->artisan('pj:company:create', [
            'email' => $user->email,
            '--name' => 'Second Co',
        ])->assertSuccessful();

        $company = Company::where('name', 'Second Co')->firstOrFail();
        $this->assertSame($user->id, $company->user_id);
        $this->assertTrue($company->hasMember($user));

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $this->assertTrue($user->fresh()->hasRole(CompanyRoleEnum::Owner->value));
    }

    public function test_fails_for_unknown_email(): void
    {
        $this->artisan('pj:company:create', ['email' => 'nobody@x.com'])
            ->assertFailed();
    }
}
