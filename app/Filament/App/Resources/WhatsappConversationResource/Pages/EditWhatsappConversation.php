<?php

namespace App\Filament\App\Resources\WhatsappConversationResource\Pages;

use App\Filament\App\Resources\WhatsappConversationResource;
use App\Services\Evolution\WhatsappConversationTokenService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatsappConversation extends EditRecord
{
    protected static string $resource = WhatsappConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(WhatsappConversationTokenService::class)->refresh($this->record);
    }
}
