<?php

namespace App\Filament\App\Resources\WhatsappConversationResource\Pages;

use App\Filament\App\Resources\WhatsappConversationResource;
use App\Jobs\SyncWhatsappConversationHistory;
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
        SyncWhatsappConversationHistory::dispatch($this->record, auth()->id());

        Notification::make()
            ->title('Sincronização iniciada')
            ->body('O histórico completo será importado em segundo plano. Você será notificado ao terminar.')
            ->success()
            ->send();
    }
}
