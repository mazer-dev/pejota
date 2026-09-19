<?php

namespace App\Filament\App\Resources\SubscriptionResource\Pages\Concerns;

use App\Filament\App\Resources\SubscriptionResource;
use App\Models\Subscription;

trait AppliesSubscriptionExtensions
{
    /** @var array<string, array<string, mixed>> */
    protected array $extensionState = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillExtensionState(array $data, Subscription $record): array
    {
        foreach (SubscriptionResource::activeExtensions() as $extension) {
            $data[$extension::stateKey()] = $extension::fillState($record);
        }

        return $data;
    }

    /**
     * DOIS laços, não um. `refuse()` recebe o estado COMPLETO do formulário, e um laço
     * único que já tivesse feito `unset` da chave da primeira extensão entregaria à
     * segunda um `$data` mutilado. Com uma extensão o defeito é invisível; com duas ele
     * é silencioso.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractExtensionState(array $data, ?Subscription $record): array
    {
        foreach (SubscriptionResource::activeExtensions() as $extension) {
            if (! array_key_exists($extension::stateKey(), $data)) {
                continue;
            }

            $extension::refuse($data, $record);
        }

        foreach (SubscriptionResource::activeExtensions() as $extension) {
            $key = $extension::stateKey();

            if (! array_key_exists($key, $data)) {
                continue;
            }

            $this->extensionState[$key] = $data[$key];

            unset($data[$key]);
        }

        return $data;
    }

    /**
     * PULA a extensão cuja chave não foi capturada, e nunca persiste um default.
     *
     * NÃO é o caso da extensão inativa: essa nem alcança o corpo do laço, porque
     * `SubscriptionResource::activeExtensions()` a filtra antes.
     *
     * A chave some quando o PRÓPRIO `Tab` da extensão é não-dehydrated — oculto sem
     * `dehydratedWhenHidden()`, ou com `dehydrated(false)`. `Component::dehydrateState()`
     * faz `Arr::forget()` sobre o statePath DO COMPONENTE
     * (`vendor/filament/schemas/src/Components/Concerns/HasState.php:342`), então é o
     * statePath do container que desaparece. Ocultar apenas os CAMPOS de dentro da aba
     * NÃO produz esse efeito: o forget cai sobre `<stateKey>.<campo>` e a chave do
     * container sobrevive como array vazio.
     *
     * Medido em 2026-09-19. Nenhuma extensão faz isso hoje, então o ramo é inalcançável
     * e sem cobertura; fica porque persistir estado vazio criaria a linha que o gate
     * existe para não criar.
     */
    protected function persistExtensionState(Subscription $record): void
    {
        foreach (SubscriptionResource::activeExtensions() as $extension) {
            $key = $extension::stateKey();

            if (! array_key_exists($key, $this->extensionState)) {
                continue;
            }

            $extension::persist($record, $this->extensionState[$key]);
        }
    }
}
