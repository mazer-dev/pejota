<?php

namespace App\Jobs;

use App\Filament\App\Resources\WhatsappConversationResource\Pages\ViewWhatsappConversation;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ai\WhatsappSuggestionService;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Analyzes a WhatsApp conversation after an inbound client message and turns
 * anything actionable into pending WhatsappSuggestion records, notifying the
 * company owner with a single consolidated database notification.
 *
 * Dispatched with a delay as a debounce: if a newer inbound message arrived
 * by the time it runs, it aborts and lets that message's own job analyze the
 * whole burst at once.
 */
class AnalyzeWhatsappConversation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The AI CLI call may take a few minutes; the worker's default 60s
     * timeout would kill the job (and the worker) before it finishes.
     */
    public int $timeout = 420;

    public int $tries = 1;

    public function __construct(
        public readonly WhatsappConversation $conversation,
        public readonly WhatsappMessage $message,
    ) {}

    public function handle(WhatsappSuggestionService $service): void
    {
        if (! config('services.ai_whatsapp_suggestions', true)) {
            return;
        }

        $conversation = $this->conversation->fresh();
        $message = $this->message->fresh();

        if (! $conversation || ! $message) {
            return;
        }

        if ($this->shouldSkip($conversation, $message)) {
            return;
        }

        $owner = $conversation->company?->owner;

        if (! $owner) {
            return;
        }

        // Context building relies on PejotaHelper, which reads the current
        // company's settings from auth()->user(). Queue workers run outside
        // any authenticated request, so the owner is logged in for the
        // lifetime of this job only (never persisted to a session).
        Auth::onceUsingId($owner->id);

        $newMessages = $conversation->messages()
            ->with('attachments')
            ->where('id', '>', (int) $conversation->last_suggested_message_id)
            ->where('id', '<=', $message->id)
            ->get();

        try {
            $suggestions = $service->analyze($conversation, $newMessages, $message);
        } catch (Throwable $exception) {
            report($exception);

            $this->notifyFailure($owner, $exception->getMessage());

            return;
        }

        $conversation->forceFill([
            'last_suggested_message_id' => $message->id,
        ])->save();

        if ($suggestions->isEmpty()) {
            return;
        }

        Notification::make()
            ->success()
            ->title(trans_choice(
                '{1} 1 sugestão da IA para a conversa :conversation|[2,*] :count sugestões da IA para a conversa :conversation',
                $suggestions->count(),
                ['count' => $suggestions->count(), 'conversation' => $conversation->display_name],
            ))
            ->body(__('Revise as sugestões na tela da conversa para aceitar ou descartar.'))
            ->actions([
                Action::make('view')
                    ->label(__('Ver conversa'))
                    ->url(ViewWhatsappConversation::getUrl([$conversation->id]))
                    ->markAsRead(),
            ])
            ->sendToDatabase($owner);
    }

    /**
     * Debounce/no-op guards: a newer inbound message means its own delayed
     * job will analyze the whole burst; an equal-or-newer analysis anchor
     * means this message was already covered.
     */
    private function shouldSkip(WhatsappConversation $conversation, WhatsappMessage $message): bool
    {
        if ((int) $conversation->last_suggested_message_id >= $message->id) {
            return true;
        }

        return $conversation->messages()
            ->where('from_me', false)
            ->where('id', '>', $message->id)
            ->exists();
    }

    /**
     * Runs when the job dies without reaching handle()'s own error
     * notification (e.g. worker timeout), so the user still hears back.
     */
    public function failed(?Throwable $exception): void
    {
        $owner = $this->conversation->company?->owner;

        if (! $owner) {
            return;
        }

        $this->notifyFailure($owner, $exception?->getMessage() ?? __('The job timed out.'));
    }

    private function notifyFailure(User $owner, ?string $reason): void
    {
        Notification::make()
            ->danger()
            ->title(__('Falha ao gerar sugestões para a conversa :conversation', [
                'conversation' => $this->conversation->display_name,
            ]))
            ->body($reason)
            ->sendToDatabase($owner);
    }
}
