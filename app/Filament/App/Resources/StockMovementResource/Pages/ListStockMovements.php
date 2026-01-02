<?php

namespace App\Filament\App\Resources\StockMovementResource\Pages;

use App\Filament\App\Resources\StockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
