<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContinuousModeEnum: string implements HasLabel
{
    case Simple = 'simple';
    case DailyCheck = 'daily_check';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Simple => __('Simple'),
            self::DailyCheck => __('Daily check'),
        };
    }
}
