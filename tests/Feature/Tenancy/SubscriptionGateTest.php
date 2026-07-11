<?php

namespace Tests\Feature\Tenancy;

use App\Contracts\SubscriptionGate;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_billing_allows_a_joined_member(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        $this->assertTrue($user->canAccessTenant($company));
    }

    public function test_denying_gate_blocks_even_a_joined_member(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        $this->app->bind(SubscriptionGate::class, fn () => new class implements SubscriptionGate
        {
            public function allows(Company $company): bool
            {
                return false;
            }
        });

        $this->assertFalse($user->canAccessTenant($company));
    }

    public function test_non_member_is_still_denied(): void
    {
        $user = User::factory()->create();
        $foreign = Company::create(['name' => 'F', 'email' => 'f@x.com', 'user_id' => $user->id]);

        $this->assertFalse($user->canAccessTenant($foreign));
    }
}
