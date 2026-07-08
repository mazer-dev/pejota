<?php

namespace App\Filament\App\Resources\WhatsappConversationResource\Pages;

use App\Filament\App\Resources\WhatsappConversationResource;
use App\Services\Evolution\WhatsappConversationSyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWhatsappConversation extends ViewRecord
{
    protected static string $resource = WhatsappConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncMessages')
                ->label('Sincronizar mensagens')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->syncMessages()),
        ];
    }

    private function syncMessages(): void
    {
        $count = app(WhatsappConversationSyncService::class)->sync($this->record);

        Notification::make()
            ->title('Mensagens sincronizadas')
            ->body(trans_choice('{0} Nenhuma mensagem foi importada.|{1} 1 mensagem foi importada.|[2,*] :count mensagens foram importadas.', $count, ['count' => $count]))
            ->success()
            ->send();
    }
}
