<?php

namespace App\Filament\App\Resources\SubscriptionResource\Pages;

use App\Filament\App\Resources\SubscriptionResource;
use App\Filament\App\Resources\SubscriptionResource\Pages\Concerns\AppliesSubscriptionExtensions;
use App\Models\Subscription;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscription extends CreateRecord
{
    use AppliesSubscriptionExtensions;

    protected static string $resource = SubscriptionResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractExtensionState($data, null);
    }

    /**
     * `CreateRecord` não tem `mutateFormDataBeforeFill`; um formulário de criação não
     * hidrata de record nenhum. Por isso só esta metade do ciclo existe aqui.
     *
     * `CreateRecord::getRecord()` é `?Model` (nullable), ao contrário do de `EditRecord`
     * — por isso o guard, em vez do `@var` que o brief original propunha.
     */
    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Subscription) {
            return;
        }

        $this->persistExtensionState($record);
    }
}
