<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DeliveryChannelEnum: string implements HasLabel
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => __('Email'),
            self::Whatsapp => __('WhatsApp'),
        };
    }
}
