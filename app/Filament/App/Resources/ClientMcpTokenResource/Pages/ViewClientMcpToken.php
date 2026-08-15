<?php

namespace App\Filament\App\Resources\ClientMcpTokenResource\Pages;

use App\Filament\App\Resources\ClientMcpTokenResource;
use App\Models\ClientMcpToken;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewClientMcpToken extends ViewRecord
{
    protected static string $resource = ClientMcpTokenResource::class;

    /**
     * Only filled right after creation, so the token can be copied once.
     */
    public ?string $plainToken = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $plainToken = session('client_mcp_token_plain');

        if (is_string($plainToken) && $plainToken !== '') {
            $this->plainToken = $plainToken;
        }
    }

    public function getTitle(): string
    {
        return __('Connection manual');
    }

    public function getSubheading(): ?string
    {
        return __('Read-only access to :client.', [
            'client' => (string) $this->getRecord()->client?->name,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revoke')
                ->label(__('Revoke'))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('The agent using this token loses access immediately.'))
                ->visible(fn (): bool => $this->getRecord() instanceof ClientMcpToken && $this->getRecord()->isActive())
                ->action(function (): void {
                    $this->getRecord()->update(['revoked_at' => now()]);
                    $this->plainToken = null;

                    Notification::make()
                        ->success()
                        ->title(__('Access revoked'))
                        ->send();
                }),
        ];
    }
}
