<?php

namespace Tests\Feature\Tenancy;

use App\Filament\App\Resources\TaskResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class PanelRoutingTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    public function test_resource_url_includes_tenant(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);

        $url = TaskResource::getUrl('index');

        $this->assertStringContainsString("/app/{$company->getKey()}/", $url);
    }

    public function test_index_page_loads_within_tenant(): void
    {
        $user = User::factory()->create();
        $this->actingInCompany($user);

        $this->get(TaskResource::getUrl('index'))->assertOk();
    }
}
