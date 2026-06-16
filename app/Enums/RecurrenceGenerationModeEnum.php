<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RecurrenceGenerationModeEnum: string implements HasLabel
{
    case ByDate = 'by_date';
    case OnCompletion = 'on_completion';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ByDate => __('By date'),
            self::OnCompletion => __('On completion'),
        };
    }
}
