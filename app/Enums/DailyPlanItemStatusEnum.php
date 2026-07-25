<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DailyPlanItemStatusEnum: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case DONE = 'done';
    case SKIPPED = 'skipped';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::DONE => __('Done'),
            self::SKIPPED => __('Skipped'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::DONE => 'success',
            self::SKIPPED => 'gray',
        };
    }
}
