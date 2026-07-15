<?php

namespace Tests\Feature\Entitlements;

use App\Contracts\FeatureGate;
use App\Enums\CompanyRoleEnum;
use App\Enums\FeatureEnum;
use App\Enums\QuotaEnum;
use App\Filament\App\Resources\ExchangeRateResource;
use App\Filament\App\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class MultiCurrencyGateTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private function actInCompany(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'email' => 'acme@example.test', 'user_id' => $user->id]);
        $user->companies()->attach($company->id, ['joined_at' => now()]);

        // CreateInvoice's mount enforces InvoicePolicy::create(), which requires this role.
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $user->assignRole(CompanyRoleEnum::Owner->value);

        $this->actingInCompany($user, $company);
    }

    private function bindMultiCurrency(bool $allow): void
    {
        $this->app->bind(FeatureGate::class, fn () => new class($allow) implements FeatureGate
        {
            public function __construct(private bool $allow) {}

            public function allows(Company $company, FeatureEnum $feature): bool
            {
                return $feature === FeatureEnum::MultiCurrency ? $this->allow : true;
            }

            public function limitFor(Company $company, QuotaEnum $quota): ?int
            {
                return null;
            }
        });
    }

    public function test_exchange_rate_resource_gated_by_multi_currency(): void
    {
        $this->actInCompany();
        $this->assertSame(FeatureEnum::MultiCurrency, ExchangeRateResource::feature());

        $this->bindMultiCurrency(false);
        $this->assertFalse(ExchangeRateResource::canAccess());

        $this->bindMultiCurrency(true);
        $this->assertTrue(ExchangeRateResource::canAccess());
    }

    public function test_invoice_currency_select_disabled_without_multi_currency(): void
    {
        $this->actInCompany();

        $this->bindMultiCurrency(false);
        Livewire::test(CreateInvoice::class)->assertFormFieldIsDisabled('currency');

        $this->bindMultiCurrency(true);
        Livewire::test(CreateInvoice::class)->assertFormFieldIsEnabled('currency');
    }
}
