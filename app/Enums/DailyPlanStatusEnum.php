<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DailyPlanStatusEnum: string implements HasColor, HasLabel
{
    case GENERATING = 'generating';
    case READY = 'ready';
    case FAILED = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::GENERATING => __('Generating'),
            self::READY => __('Ready'),
            self::FAILED => __('Failed'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::GENERATING => 'warning',
            self::READY => 'success',
            self::FAILED => 'danger',
        };
    }
}
