<?php

namespace Tests\Feature\Entitlements;

use App\Contracts\FeatureGate;
use App\Enums\FeatureEnum;
use App\Enums\QuotaEnum;
use App\Filament\App\Concerns\GatesAccessByFeature;
use App\Models\Company;
use App\Models\User;
use App\Support\Entitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class GatesAccessByFeatureTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private function fakeGate(bool $allow): void
    {
        $this->app->bind(FeatureGate::class, fn () => new class($allow) implements FeatureGate
        {
            public function __construct(private bool $allow) {}

            public function allows(Company $company, FeatureEnum $feature): bool
            {
                return $this->allow;
            }

            public function limitFor(Company $company, QuotaEnum $quota): ?int
            {
                return null;
            }
        });
    }

    private function gatedResource(): string
    {
        return (new class extends \Filament\Resources\Resource
        {
            use GatesAccessByFeature;

            public static function feature(): FeatureEnum
            {
                return FeatureEnum::Contracts;
            }
        })::class;
    }

    public function test_canaccess_follows_the_gate_within_an_active_company(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'email' => 'acme@x.com', 'user_id' => $user->id]);
        $user->companies()->attach($company->id, ['joined_at' => now()]);
        $this->actingInCompany($user, $company); // faz currentCompany() resolver

        $resource = $this->gatedResource();

        $this->fakeGate(false);
        $this->assertFalse($resource::canAccess());

        $this->fakeGate(true);
        $this->assertTrue($resource::canAccess());
    }

    /** Absorve o Minor da A1: fachada Entitlements sem tenant → default permissivo. */
    public function test_entitlements_falls_back_to_permissive_default_without_a_company(): void
    {
        // sem actingInCompany → PejotaHelper::currentCompany() é null
        $this->app->bind(FeatureGate::class, fn () => new class implements FeatureGate
        {
            public function allows(Company $company, FeatureEnum $feature): bool
            {
                return false; // se fosse consultado, negaria — prova que NÃO é consultado
            }

            public function limitFor(Company $company, QuotaEnum $quota): ?int
            {
                return 0;
            }
        });

        $this->assertTrue(Entitlements::allows(FeatureEnum::Contracts));
        $this->assertNull(Entitlements::limitFor(QuotaEnum::TasksPerMonth));
        $this->assertTrue(Entitlements::withinQuota(QuotaEnum::TasksPerMonth, 999));
    }
}
