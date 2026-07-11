<?php

namespace Tests\Feature\Provisioning;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_flagged_user_does_not_get_an_auto_company(): void
    {
        $user = new User(['name' => 'Invited', 'email' => 'invited@x.com', 'password' => 'secret-pass']);
        $user->skipCompanyProvisioning = true;
        $user->save();

        $this->assertSame(0, $user->companies()->count());
    }

    public function test_unflagged_user_still_gets_an_auto_company(): void
    {
        $user = User::factory()->create();

        $this->assertSame(1, $user->companies()->count());
    }
}
