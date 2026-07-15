<?php

namespace Tests\Feature\Entitlements;

use App\Billing\NullFeatureGate;
use App\Contracts\FeatureGate;
use App\Enums\FeatureEnum;
use App\Enums\QuotaEnum;
use App\Models\Company;
use Tests\TestCase;

class FeatureGateBindingTest extends TestCase
{
    public function test_feature_gate_contract_is_bound(): void
    {
        // Assert the contract, not the concrete impl: it is NullFeatureGate in open-core
        // and PlanFeatureGate under the cloud overlay, so asserting the class would break
        // in the cloud. Mirrors SubscriptionGateTest (behavior via a fake, not the default).
        $this->assertInstanceOf(FeatureGate::class, app(FeatureGate::class));
    }

    public function test_null_feature_gate_allows_every_feature_and_leaves_quotas_unlimited(): void
    {
        $gate = new NullFeatureGate;
        $company = new Company;

        foreach (FeatureEnum::cases() as $feature) {
            $this->assertTrue($gate->allows($company, $feature));
        }
        foreach (QuotaEnum::cases() as $quota) {
            $this->assertNull($gate->limitFor($company, $quota));
        }
    }

    public function test_enum_values_are_the_canonical_strings(): void
    {
        $this->assertSame('contracts', FeatureEnum::Contracts->value);
        $this->assertSame('multi_currency', FeatureEnum::MultiCurrency->value);
        $this->assertSame('recurring_tasks', FeatureEnum::RecurringTasks->value);
        $this->assertSame('tasks_per_month', QuotaEnum::TasksPerMonth->value);
        $this->assertSame('active_projects', QuotaEnum::ActiveProjects->value);
    }
}
