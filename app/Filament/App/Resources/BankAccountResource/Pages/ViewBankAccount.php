<?php

namespace App\Filament\App\Resources\BankAccountResource\Pages;

use App\Filament\App\Resources\BankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBankAccount extends ViewRecord
{
    protected static string $resource = BankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}