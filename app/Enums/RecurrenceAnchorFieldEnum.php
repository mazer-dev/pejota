<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RecurrenceAnchorFieldEnum: string implements HasLabel
{
    case DueDate = 'due_date';
    case PlannedEnd = 'planned_end';
    case Both = 'both';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DueDate => __('Due date'),
            self::PlannedEnd => __('Planned end'),
            self::Both => __('Both'),
        };
    }
}
