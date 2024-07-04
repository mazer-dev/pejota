<?php

namespace App\Tables\Columns;

use Filament\Support\Concerns\HasColor;
use Filament\Tables\Columns\Column;

class BlockTypesBadge extends Column
{
    use HasColor;

    protected string $view = 'tables.columns.block-types-badge';
}
