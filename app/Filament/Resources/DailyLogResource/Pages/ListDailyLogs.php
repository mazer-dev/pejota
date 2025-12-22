<?php

namespace App\Filament\Resources\DailyLogResource\Pages;

use App\Filament\Resources\DailyLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDailyLogs extends ListRecords
{
    protected static string $resource = DailyLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
