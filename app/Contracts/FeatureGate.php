<?php

namespace App\Contracts;

use App\Enums\FeatureEnum;
use App\Enums\QuotaEnum;
use App\Models\Company;

interface FeatureGate
{
    public function allows(Company $company, FeatureEnum $feature): bool;

    /** @return int|null Null = ilimitado. */
    public function limitFor(Company $company, QuotaEnum $quota): ?int;
}
