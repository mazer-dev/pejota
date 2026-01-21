<?php

namespace App\Filament\App\Resources\PurchaseItemResource\Pages;

use App\Filament\App\Resources\PurchaseItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseItem extends EditRecord
{
    protected static string $resource = PurchaseItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
