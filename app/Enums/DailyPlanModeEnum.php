<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DailyPlanModeEnum: string implements HasLabel
{
    case FULL = 'full';
    case LIGHT = 'light';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FULL => __('Work day'),
            self::LIGHT => __('Day off'),
        };
    }
}
