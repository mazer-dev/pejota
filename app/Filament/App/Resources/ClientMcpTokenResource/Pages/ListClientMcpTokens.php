<?php

namespace App\Filament\App\Resources\ClientMcpTokenResource\Pages;

use App\Filament\App\Resources\ClientMcpTokenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClientMcpTokens extends ListRecords
{
    protected static string $resource = ClientMcpTokenResource::class;

    public function getSubheading(): ?string
    {
        $clients = ClientMcpTokenResource::getEloquentQuery()
            ->whereNull('revoked_at')
            ->distinct()
            ->count('client_id');

        return trans_choice(
            '{0}No client is exposed via MCP right now.|{1}1 client is available via MCP, read only.|[2,*]:count clients are available via MCP, read only.',
            $clients,
            ['count' => $clients]
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('New MCP access')),
        ];
    }
}
