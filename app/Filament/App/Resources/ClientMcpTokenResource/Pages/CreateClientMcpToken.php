<?php

namespace App\Filament\App\Resources\ClientMcpTokenResource\Pages;

use App\Filament\App\Resources\ClientMcpTokenResource;
use App\Models\ClientMcpToken;
use Filament\Resources\Pages\CreateRecord;

class CreateClientMcpToken extends CreateRecord
{
    protected static string $resource = ClientMcpTokenResource::class;

    /**
     * Lets the "MCP access" action on the client list preselect the client.
     */
    public function mount(): void
    {
        parent::mount();

        $clientId = (int) request()->query('client_id');

        if ($clientId > 0) {
            $this->form->fill([
                'client_id' => $clientId,
                'name' => 'Claude Code',
            ]);
        }
    }

    /**
     * The plain token exists only here: the database keeps its hash, and the
     * view page shows it once through the session.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $plainToken = ClientMcpToken::generatePlainToken();

        session()->flash('client_mcp_token_plain', $plainToken);

        $data['token_hash'] = ClientMcpToken::hashToken($plainToken);
        $data['company_id'] = auth()->user()->company->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ClientMcpTokenResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    public function getTitle(): string
    {
        return __('New MCP access');
    }
}
