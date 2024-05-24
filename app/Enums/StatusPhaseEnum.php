<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StatusPhaseEnum: string implements HasLabel
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case CLOSED = 'closed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TODO => __('Todo'),
            self::IN_PROGRESS => __('In Progress'),
            self::CLOSED => __('Closed'),
        };
    }
}
