<?php

namespace Tests\Feature\Entitlements;

use App\Contracts\FeatureGate;
use App\Enums\FeatureEnum;
use App\Enums\QuotaEnum;
use App\Filament\App\Resources\AccountResource;
use App\Filament\App\Resources\ContractResource;
use App\Filament\App\Resources\ProductResource;
use App\Filament\App\Resources\SubscriptionResource;
use App\Filament\App\Resources\VendorResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class PaidResourcesGatedTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    /** @return array<string, array{class-string, FeatureEnum}> */
    public static function paidResources(): array
    {
        return [
            'contract' => [ContractResource::class, FeatureEnum::Contracts],
            'product' => [ProductResource::class, FeatureEnum::Products],
            'account' => [AccountResource::class, FeatureEnum::Accounts],
            'subscription' => [SubscriptionResource::class, FeatureEnum::DomainSubscriptions],
            'vendor' => [VendorResource::class, FeatureEnum::Vendors],
        ];
    }

    private function actInCompany(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'email' => 'acme@example.test', 'user_id' => $user->id]);
        $user->companies()->attach($company->id, ['joined_at' => now()]);
        $this->actingInCompany($user, $company);
    }

    /**
     * @param  class-string  $resource
     */
    #[DataProvider('paidResources')]
    public function test_paid_resource_declares_its_feature_and_hides_when_denied(string $resource, FeatureEnum $feature): void
    {
        $this->actInCompany();
        $this->assertSame($feature, $resource::feature());

        $this->app->bind(FeatureGate::class, fn () => new class implements FeatureGate
        {
            public function allows(Company $company, FeatureEnum $feature): bool
            {
                return false;
            }

            public function limitFor(Company $company, QuotaEnum $quota): ?int
            {
                return null;
            }
        });

        $this->assertFalse($resource::canAccess());
    }

    /**
     * @param  class-string  $resource
     */
    #[DataProvider('paidResources')]
    public function test_paid_resource_visible_under_null_gate(string $resource, FeatureEnum $feature): void
    {
        $this->actInCompany();
        $this->assertTrue($resource::canAccess()); // NullFeatureGate (default do core)
    }
}
