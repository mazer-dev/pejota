<?php

namespace App\Filament\App\Concerns;

use App\Enums\FeatureEnum;
use App\Support\Entitlements;

trait GatesAccessByFeature
{
    abstract public static function feature(): FeatureEnum;

    public static function canAccess(): bool
    {
        return Entitlements::allows(static::feature());
    }
}
