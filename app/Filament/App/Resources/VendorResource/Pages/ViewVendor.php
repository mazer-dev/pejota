<?php

namespace App\Filament\App\Resources\VendorResource\Pages;

use App\Filament\App\Resources\VendorResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVendor extends ViewRecord
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
