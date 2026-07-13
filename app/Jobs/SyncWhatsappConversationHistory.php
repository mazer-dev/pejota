<?php

namespace App\Jobs;

use App\Filament\App\Resources\WhatsappConversationResource\Pages\ViewWhatsappConversation;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Services\Evolution\WhatsappConversationSyncService;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SyncWhatsappConversationHistory implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public int $uniqueFor = 1800;

    public function __construct(
        public readonly WhatsappConversation $conversation,
        public readonly ?int $requestedById = null,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->conversation->getKey();
    }

    public function handle(WhatsappConversationSyncService $syncService): void
    {
        $conversation = $this->conversation->fresh();
        if (! $conversation) {
            return;
        }

        $user = $this->notificationUser($conversation);
        if ($user) {
            Auth::onceUsingId($user->id);
        }

        $count = $syncService->syncAll($conversation);

        if (! $user) {
            return;
        }

        Notification::make()
            ->success()
            ->title(__('Histórico do WhatsApp sincronizado'))
            ->body(trans_choice(
                '{0} Nenhuma mensagem nova foi importada para :conversation.|{1} 1 mensagem foi importada para :conversation.|[2,*] :count mensagens foram importadas para :conversation.',
                $count,
                ['count' => $count, 'conversation' => $conversation->display_name],
            ))
            ->actions([
                Action::make('view')
                    ->label(__('Ver conversa'))
                    ->url(ViewWhatsappConversation::getUrl([$conversation->id]))
                    ->markAsRead(),
            ])
            ->sendToDatabase($user);
    }

    public function failed(?Throwable $exception): void
    {
        $conversation = $this->conversation->fresh() ?: $this->conversation;
        $user = $this->notificationUser($conversation);

        if (! $user) {
            return;
        }

        Notification::make()
            ->danger()
            ->title(__('Falha ao sincronizar o histórico do WhatsApp'))
            ->body(__('A importação de :conversation parou parcialmente e pode ser repetida com segurança. :reason', [
                'conversation' => $conversation->display_name,
                'reason' => $exception?->getMessage() ?? '',
            ]))
            ->sendToDatabase($user);
    }

    private function notificationUser(WhatsappConversation $conversation): ?User
    {
        if ($this->requestedById) {
            $user = User::query()->find($this->requestedById);
            if ($user && $user->company?->id === $conversation->company_id) {
                return $user;
            }
        }

        return $conversation->company?->owner;
    }
}
