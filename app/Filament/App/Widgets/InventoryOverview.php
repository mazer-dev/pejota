<?php

namespace App\Filament\App\Widgets;
use App\Models\Material;
use App\Models\Branch;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
           Stat::make('Total Materials', Material::count())
                ->description('Items in catalog')
                ->color('primary'),

            Stat::make('Storage Locations', Branch::count())
                ->description('Active branches/sites'),

            Stat::make('Low Stock Items', Material::whereRaw('reorder_point > 0')->count())
                ->description('Needs reorder')
                ->color('danger'),
        ];
    }
}
