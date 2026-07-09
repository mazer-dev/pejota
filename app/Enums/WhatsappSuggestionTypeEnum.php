<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WhatsappSuggestionTypeEnum: string implements HasLabel
{
    case Task = 'task';
    case Note = 'note';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Task => __('Task'),
            self::Note => __('Note'),
        };
    }
}
