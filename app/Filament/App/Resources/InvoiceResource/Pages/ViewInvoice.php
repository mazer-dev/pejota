<?php

namespace App\Filament\App\Resources\InvoiceResource\Pages;

use App\Filament\App\Resources\InvoiceResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InvoiceResource::configureChangeStatusAction(Action::make('change_status')),
            InvoiceResource::configureSendAction(Action::make('send')),
            EditAction::make(),
            Action::make('pdf')
                ->label('PDF')
                ->color('info')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function (Model $record) {
                    return InvoiceResource::generatePdf($record);
                }),
            Action::make('clone')
                ->translateLabel()
                ->color('gray')
                ->icon('heroicon-o-document-duplicate')
                ->url(fn (Model $record) => InvoiceResource::getUrl('create', ['clone' => $record->id])),
        ];
    }
}
