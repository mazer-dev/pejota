<?php

namespace App\Filament\App\Resources\PurchaseItemResource\Pages;

use App\Filament\App\Resources\PurchaseItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseItems extends ListRecords
{
    protected static string $resource = PurchaseItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
