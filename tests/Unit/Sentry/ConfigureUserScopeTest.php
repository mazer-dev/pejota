<?php

namespace Tests\Unit\Sentry;

use App\Models\Company;
use App\Models\User;
use App\Sentry\ConfigureUserScope;
use Tests\TestCase;

class ConfigureUserScopeTest extends TestCase
{
    public function test_data_includes_user_and_company(): void
    {
        $user = new User(['name' => 'Ada', 'email' => 'ada@example.com']);
        $user->id = 7;
        $company = new Company(['name' => 'Acme']);
        $company->id = 42;
        $user->setRelation('company', $company);

        $data = (new ConfigureUserScope)->data($user);

        $this->assertSame(['id' => 7, 'email' => 'ada@example.com'], $data['user']);
        $this->assertSame('42', $data['tags']['company']);
        $this->assertSame(['id' => 42, 'name' => 'Acme'], $data['context']);
    }

    public function test_data_without_company_has_only_user(): void
    {
        $user = new User(['email' => 'solo@example.com']);
        $user->id = 9;
        $user->setRelation('company', null);

        $data = (new ConfigureUserScope)->data($user);

        $this->assertSame(['id' => 9, 'email' => 'solo@example.com'], $data['user']);
        $this->assertArrayNotHasKey('tags', $data);
        $this->assertArrayNotHasKey('context', $data);
    }

    public function test_data_for_null_user_is_empty(): void
    {
        $this->assertSame([], (new ConfigureUserScope)->data(null));
    }
}
