<?php

namespace Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompanyUserRoleColumnDroppedTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_column_is_gone_from_pivot(): void
    {
        $this->assertFalse(Schema::hasColumn('company_user', 'role'));
    }
}
