<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RecurrenceStopTypeEnum: string implements HasLabel
{
    case Never = 'never';
    case UntilDate = 'until_date';
    case Count = 'count';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Never => __('Never'),
            self::UntilDate => __('Until date'),
            self::Count => __('Number of occurrences'),
        };
    }
}
