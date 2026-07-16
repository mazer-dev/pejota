<?php

namespace App\Filament\App\Pages;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    public function getColumns(): int|array
    {
        return 6;
    }
}
