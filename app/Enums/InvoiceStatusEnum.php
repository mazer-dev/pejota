<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum InvoiceStatusEnum: string implements HasLabel, HasColor, HasIcon, HasDescription
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case UNPAID = 'unpaid';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case CANCELED = 'canceled';

    public function getLabel(): string
    {
        return __(
            Str::ucfirst(
                Str::replace('_', ' ',  $this->value)
            )
        );
    }

    public function getDescription(): ?string
    {
        return $this->getLabel();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'info',
            self::UNPAID => 'warning',
            self::PARTIALLY_PAID => 'warning',
            self::PAID => 'success',
            self::CANCELED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-document-duplicate',
            self::SENT => 'heroicon-o-document',
            self::UNPAID => 'heroicon-o-x-circle',
            self::PARTIALLY_PAID => 'heroicon-o-clipboard-document-check',
            self::PAID => 'heroicon-o-check',
            self::CANCELED => 'heroicon-o-exclamation-circle',
        };
    }

}
