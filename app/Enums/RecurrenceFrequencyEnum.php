<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RecurrenceFrequencyEnum: string implements HasLabel
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Weekly => __('Weekly'),
            self::Monthly => __('Monthly'),
            self::Yearly => __('Yearly'),
        };
    }
}
