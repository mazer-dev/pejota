<?php

namespace App\Jobs;

use App\Filament\App\Resources\ClientResource\Pages\ViewClient;
use App\Models\Client;
use App\Models\User;
use App\Services\Ai\ClientAnalysisService;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Throwable;

class GenerateClientAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Client $client,
        public readonly User $user,
    ) {}

    public function handle(ClientAnalysisService $service): void
    {
        // Context building relies on PejotaHelper, which reads the current
        // company's settings from auth()->user(). Queue workers run outside
        // any authenticated request, so the requesting user is logged in
        // for the lifetime of this job only (never persisted to a session).
        Auth::onceUsingId($this->user->id);

        try {
            $service->generate($this->client->fresh());
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(__('Failed to generate analysis for :client', ['client' => $this->client->name]))
                ->body($exception->getMessage())
                ->sendToDatabase($this->user);

            return;
        }

        Notification::make()
            ->success()
            ->title(__('Analysis for :client is ready', ['client' => $this->client->name]))
            ->body(__('A new relationship analysis has been generated.'))
            ->actions([
                Action::make('view')
                    ->label(__('View client'))
                    ->url(ViewClient::getUrl([$this->client->id]))
                    ->markAsRead(),
            ])
            ->sendToDatabase($this->user);
    }
}
