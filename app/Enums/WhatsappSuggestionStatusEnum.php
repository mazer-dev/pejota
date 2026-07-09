<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WhatsappSuggestionStatusEnum: string implements HasLabel
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Dismissed = 'dismissed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Accepted => __('Accepted'),
            self::Dismissed => __('Dismissed'),
        };
    }
}
