<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MailEncryptionEnum: string implements HasLabel
{
    case Tls = 'tls';
    case Ssl = 'ssl';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tls => 'TLS',
            self::Ssl => 'SSL',
        };
    }
}
