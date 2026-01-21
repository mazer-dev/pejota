<?php

namespace App\Filament\Resources\DailyLogResource\Pages;

use App\Filament\Resources\DailyLogResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDailyLog extends CreateRecord
{
    protected static string $resource = DailyLogResource::class;
}
