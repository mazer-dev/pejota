<?php

namespace App\Filament\App\Resources\InvoiceResource\Pages;

use App\Filament\App\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('pdf')
                ->label('PDF')
                ->color('info')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function (Model $record) {
                    return InvoiceResource::generatePdf($record);
                }),
            Actions\Action::make('clone')
                ->translateLabel()
                ->color('gray')
                ->icon('heroicon-o-document-duplicate')
                ->url(fn (Model $record) => InvoiceResource::getUrl('create', ['clone' => $record->id])),
        ];
    }
}
