<?php

namespace App\Support;

use App\Contracts\FeatureGate;
use App\Enums\FeatureEnum;
use App\Enums\QuotaEnum;
use App\Helpers\PejotaHelper;

class Entitlements
{
    public static function allows(FeatureEnum $feature): bool
    {
        $company = PejotaHelper::currentCompany();

        return $company === null
            ? true
            : app(FeatureGate::class)->allows($company, $feature);
    }

    public static function limitFor(QuotaEnum $quota): ?int
    {
        $company = PejotaHelper::currentCompany();

        return $company === null
            ? null
            : app(FeatureGate::class)->limitFor($company, $quota);
    }

    public static function withinQuota(QuotaEnum $quota, int $current): bool
    {
        $limit = self::limitFor($quota);

        return $limit === null || $current < $limit;
    }
}
