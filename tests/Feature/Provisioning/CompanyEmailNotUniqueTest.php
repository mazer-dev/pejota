<?php

namespace Tests\Feature\Provisioning;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyEmailNotUniqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_companies_can_share_the_same_email(): void
    {
        $user = User::factory()->create();

        // A 1ª empresa (auto-criada pelo listener) já usa user.email.
        $first = $user->companies()->wherePivotNotNull('joined_at')->firstOrFail();

        // Uma 2ª empresa com o MESMO email não pode mais colidir.
        $second = Company::create([
            'user_id' => $user->id,
            'name' => 'Second',
            'email' => $first->email,
        ]);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($first->email, $second->email);
    }
}
