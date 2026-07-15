<?php

namespace App\Billing;

use App\Contracts\FeatureGate;
use App\Enums\FeatureEnum;
use App\Enums\QuotaEnum;
use App\Models\Company;

class NullFeatureGate implements FeatureGate
{
    public function allows(Company $company, FeatureEnum $feature): bool
    {
        return true;
    }

    public function limitFor(Company $company, QuotaEnum $quota): ?int
    {
        return null;
    }
}
