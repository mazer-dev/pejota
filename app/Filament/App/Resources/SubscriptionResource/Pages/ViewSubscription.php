<?php

namespace App\Filament\App\Resources\SubscriptionResource\Pages;

use App\Filament\App\Resources\SubscriptionResource;
use App\Filament\App\Resources\SubscriptionResource\Pages\Concerns\AppliesSubscriptionExtensions;
use App\Models\Subscription;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscription extends ViewRecord
{
    use AppliesSubscriptionExtensions;

    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * `ViewRecord::mount()` cai em `fillForm()` quando o resource não define
     * `infolist()` — que é o caso aqui —, e `fillForm()` chama este hook antes de
     * preencher o schema. Sem ele, a aba da extensão hidrata com o estado default do
     * componente, não o do record: só a metade de leitura do trait faz sentido numa
     * página que não grava (`extractExtensionState`/`persistExtensionState` ficam para
     * `EditSubscription`/`CreateSubscription`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Subscription $record */
        $record = $this->getRecord();

        return $this->fillExtensionState($data, $record);
    }
}
