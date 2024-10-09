<?php

namespace App\Filament\App\Pages;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

}
