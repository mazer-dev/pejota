<?php

namespace Tests\Feature;

use App\Events\CompanyCreated;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CompanyCreatedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_service_create_dispatches_company_created(): void
    {
        Event::fake([CompanyCreated::class]);

        $user = User::factory()->create();
        $company = app(CompanyService::class)->create($user, 'Acme', 'a@x.com');

        Event::assertDispatched(CompanyCreated::class, function (CompanyCreated $event) use ($company): bool {
            return $event->company->is($company);
        });
    }

    public function test_raw_company_create_does_not_dispatch_company_created(): void
    {
        $user = User::factory()->create();

        Event::fake([CompanyCreated::class]);
        Company::create(['name' => 'Raw', 'email' => 'raw@x.com', 'user_id' => $user->id]);

        Event::assertNotDispatched(CompanyCreated::class);
    }
}
