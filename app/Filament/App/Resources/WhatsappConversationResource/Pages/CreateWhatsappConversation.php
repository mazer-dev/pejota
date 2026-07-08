<?php

namespace App\Filament\App\Resources\WhatsappConversationResource\Pages;

use App\Filament\App\Resources\WhatsappConversationResource;
use App\Services\Evolution\WhatsappConversationSyncService;
use Filament\Notifications\Notification;
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

    protected function afterCreate(): void
    {
        $count = app(WhatsappConversationSyncService::class)->sync($this->record);

        if ($count === 0) {
            return;
        }

        Notification::make()
            ->title('Mensagens sincronizadas')
            ->body(trans_choice('{1} 1 mensagem foi importada.|[2,*] :count mensagens foram importadas.', $count, ['count' => $count]))
            ->success()
            ->send();
    }
}
