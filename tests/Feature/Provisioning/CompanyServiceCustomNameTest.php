<?php

namespace Tests\Feature\Provisioning;

use App\Enums\CompanyRoleEnum;
use App\Events\UserCreated;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompanyServiceCustomNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_uses_provided_name_and_email(): void
    {
        $user = User::factory()->create();

        $company = app(CompanyService::class)->create($user, 'Acme Ltda', 'acme@x.com');

        $this->assertSame('Acme Ltda', $company->name);
        $this->assertSame('acme@x.com', $company->email);
        $this->assertSame($user->id, $company->user_id);
        $this->assertTrue($company->hasMember($user));

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $this->assertTrue($user->fresh()->hasRole(CompanyRoleEnum::Owner->value));
    }

    public function test_create_defaults_to_user_name_and_email(): void
    {
        // Faking UserCreated prevents the CreateCompanyForUser listener from
        // auto-provisioning a company for this user (same default name/email),
        // which would otherwise collide with the explicit call below on the
        // unique companies.email constraint.
        Event::fake([UserCreated::class]);

        $user = User::factory()->create(['name' => 'Jane', 'email' => 'jane@x.com']);

        $company = app(CompanyService::class)->create($user);

        $this->assertSame('Jane', $company->name);
        $this->assertSame('jane@x.com', $company->email);
    }

    public function test_empty_strings_fall_back_to_user_defaults(): void
    {
        $user = User::factory()->create(['name' => 'Zoe', 'email' => 'zoe@x.com']);

        // A blank optional email field can arrive as '' (not null); it must fall
        // back to the owner's value, not persist as an empty string. `??` would
        // keep the '' — `filled()` is what makes this pass.
        $company = app(CompanyService::class)->create($user, '', '');

        $this->assertSame('Zoe', $company->name);
        $this->assertSame('zoe@x.com', $company->email);
    }
}
