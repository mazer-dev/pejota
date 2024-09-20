<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatusEnum: string implements HasLabel, HasColor, HasIcon
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PAID = 'paid';
    case CANCELED = 'canceled';

    public function getLabel(): string
    {
        return ucwords(__($this->value));
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'info',
            self::PAID => 'success',
            self::CANCELED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-document-duplicate',
            self::SENT => 'heroicon-o-document',
            self::PAID => 'heroicon-o-check',
            self::CANCELED => 'heroicon-o-x-circle',
        };
    }

}
