<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatusEnum: string implements HasLabel, HasIcon, HasColor
{
    case TRIAL = 'trial';
    case ACTIVE = 'active';
    case CANCELED = 'canceled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TRIAL => 'Trial',
            self::ACTIVE => 'Active',
            self::CANCELED => 'Canceled',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::TRIAL => 'heroicon-o-clock',
            self::ACTIVE => 'heroicon-o-check-circle',
            self::CANCELED => 'heroicon-o-x-circle',
        };
    }

    public function getColor(): ?array
    {
        return match ($this) {
            self::TRIAL => Color::Orange,
            self::ACTIVE => Color::Green,
            self::CANCELED => Color::Red,
        };
    }
}
