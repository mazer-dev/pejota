<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubscriptionBillingPeriodEnum: string implements HasLabel
{
    case MONTHLY = 'monthly';
    case THREE_MONTHS = 'three_months';
    case SIX_MONTHS = 'six_months';
    case YEARLY = 'yearly';
    case LIFETIME = 'lifetime';

    public function getLabel(): string
    {
        return match ($this) {
            self::MONTHLY => 'Monthly',
            self::THREE_MONTHS => 'Every 3 Months',
            self::SIX_MONTHS => 'Every 6 Months',
            self::YEARLY => 'Yearly',
            self::LIFETIME => 'Lifetime',
        };
    }

    public function getPeriod(): string
    {
        return match ($this) {
            self::MONTHLY => '+1 month',
            self::THREE_MONTHS => '+3 months',
            self::SIX_MONTHS => '+6 months',
            self::YEARLY => '+1 year',
            self::LIFETIME => '+100 years', // Assuming lifetime as 100 years
        };
    }
}
