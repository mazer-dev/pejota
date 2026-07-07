<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MailDriverEnum: string implements HasLabel
{
    case Smtp = 'smtp';

    public function getLabel(): string
    {
        return match ($this) {
            self::Smtp => __('SMTP'),
        };
    }
}
