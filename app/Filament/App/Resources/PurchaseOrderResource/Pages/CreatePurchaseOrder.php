<?php

namespace App\Filament\App\Resources\PurchaseOrderResource\Pages;

use App\Filament\App\Resources\PurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;
}
