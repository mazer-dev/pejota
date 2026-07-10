<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_creates_a_single_company_with_owner_membership(): void
    {
        $this->artisan('pj:install')
            ->expectsQuestion('Please enter the user name, enter for "Admin"', 'Jane Doe')
            ->expectsQuestion('Please enter the user email or enter for admin@admin.com', 'jane@example.com')
            ->expectsQuestion('Please enter the user password or enter for "123456"', 'secret123')
            ->expectsQuestion('Please enter the company name, enter for "My Company"', 'Doe Consulting')
            ->expectsQuestion('Please enter the company email or enter for empty', 'contact@doeconsulting.com')
            ->assertSuccessful();

        $this->assertSame(1, Company::count());

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertSame(1, $user->companies()->count());

        $company = $user->companies()->first();

        $this->assertSame('Doe Consulting', $company->name);
        $this->assertSame('contact@doeconsulting.com', $company->email);
        $this->assertSame('owner', $company->pivot->role);
        $this->assertNotNull($company->pivot->joined_at);
    }
}
