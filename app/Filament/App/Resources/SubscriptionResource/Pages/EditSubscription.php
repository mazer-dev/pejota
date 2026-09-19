<?php

namespace App\Filament\App\Resources\SubscriptionResource\Pages;

use App\Filament\App\Resources\SubscriptionResource;
use App\Filament\App\Resources\SubscriptionResource\Pages\Concerns\AppliesSubscriptionExtensions;
use App\Models\Subscription;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscription extends EditRecord
{
    use AppliesSubscriptionExtensions;

    protected static string $resource = SubscriptionResource::class;

    /**
     * `Panel::$hasDatabaseTransactions` é `false` por padrão e `AppPanelProvider` não
     * chama `databaseTransactions()`, então sem esta linha o save da extensão e o da
     * assinatura não são atômicos. Ligado aqui, e não no painel, para não mudar o
     * comportamento de todos os resources de uma vez.
     */
    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Subscription $record */
        $record = $this->getRecord();

        return $this->fillExtensionState($data, $record);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Subscription $record */
        $record = $this->getRecord();

        return $this->extractExtensionState($data, $record);
    }

    protected function afterSave(): void
    {
        /** @var Subscription $record */
        $record = $this->getRecord();

        $this->persistExtensionState($record);
    }
}
