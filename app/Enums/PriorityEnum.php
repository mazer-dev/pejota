<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PriorityEnum: string implements HasColor, HasIcon, HasLabel
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';
    case CRITICAL = 'critical';

    public function getLabel(): ?string
    {
        return __(
            ucwords(
                str_replace('_', ' ', $this->value)
            )
        );
    }

    public function getOrder(): ?int
    {
        return match ($this) {
            self::LOW => 0,
            self::MEDIUM => 1,
            self::HIGH => 2,
            self::URGENT => 3,
            self::CRITICAL => 4,
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::CRITICAL => 'heroicon-o-exclamation-circle',
            self::URGENT => 'heroicon-o-chevron-double-up',
            self::HIGH => 'heroicon-o-chevron-up',
            self::MEDIUM => 'heroicon-o-minus',
            self::LOW => 'heroicon-o-chevron-down',
        };
    }

    public function getColor(): ?array
    {
        return match ($this) {
            self::CRITICAL => Color::Red,
            self::URGENT => Color::Orange,
            self::HIGH => Color::Yellow,
            self::MEDIUM => Color::Blue,
            self::LOW => Color::Neutral,
        };
    }
}
