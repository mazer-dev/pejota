<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PriorityEnum: string implements HasLabel
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';
    case CRITICAL = 'critical';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
            self::URGENT => 'Urgent',
            self::CRITICAL => 'Critical',
        };
    }
}
