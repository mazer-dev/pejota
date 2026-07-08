<?php

namespace App\Filament\App\Resources\WhatsappConversationResource\Pages;

use App\Filament\App\Resources\WhatsappConversationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWhatsappConversation extends CreateRecord
{
    protected static string $resource = WhatsappConversationResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return WhatsappConversationResource::prepareConversationData($data);
    }
}
