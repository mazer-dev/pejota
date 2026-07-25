<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DailyPlanItemTypeEnum: string implements HasColor, HasIcon, HasLabel
{
    case TASK = 'task';
    case FOLLOW_UP = 'follow_up';
    case INVOICE = 'invoice';
    case CONTRACT = 'contract';
    case HABIT = 'habit';
    case ADMIN = 'admin';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TASK => __('Task'),
            self::FOLLOW_UP => __('Follow-up'),
            self::INVOICE => __('Invoice'),
            self::CONTRACT => __('Contract'),
            self::HABIT => __('Habit'),
            self::ADMIN => __('Admin'),
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::TASK => 'heroicon-o-check-circle',
            self::FOLLOW_UP => 'heroicon-o-chat-bubble-left-right',
            self::INVOICE => 'heroicon-o-banknotes',
            self::CONTRACT => 'heroicon-o-document-text',
            self::HABIT => 'heroicon-o-fire',
            self::ADMIN => 'heroicon-o-briefcase',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TASK => Color::Blue,
            self::FOLLOW_UP => Color::Orange,
            self::INVOICE => Color::Green,
            self::CONTRACT => Color::Purple,
            self::HABIT => Color::Red,
            self::ADMIN => Color::Neutral,
        };
    }
}
