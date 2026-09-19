<?php

namespace App\Contracts;

use App\Models\Subscription;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;

/**
 * An extra tab on the subscription resource, with its own persistence.
 *
 * Implementations are registered in `config('pejota.subscription_resource_extensions')`.
 * Open core registers none; the seam exists so an overlay can attach data that belongs
 * next to a subscription without publishing a resource of its own.
 *
 * `isActive()` gates the WHOLE cycle. An inactive extension composes no tab, hydrates
 * nothing, refuses nothing and — most importantly — persists nothing, so a downgraded
 * tenant saves the subscription without touching the rows the extension owns.
 */
interface SubscriptionResourceExtension
{
    public static function isActive(): bool;

    /** The key this extension's state is nested under, matching its tab's `statePath()`. */
    public static function stateKey(): string;

    public static function tab(): Tab;

    /** @return array<string, mixed> */
    public static function fillState(Subscription $record): array;

    /**
     * Runs BEFORE the subscription row is written, with the COMPLETE form state.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Halt to refuse the save
     */
    public static function refuse(array $data, ?Subscription $record): void;

    /**
     * Runs AFTER the subscription row is written, inside the same transaction.
     *
     * @param  array<string, mixed>  $state
     */
    public static function persist(Subscription $record, array $state): void;

    /** @return array<int, Column> */
    public static function columns(): array;

    /** @return array<int, BaseFilter> */
    public static function filters(): array;

    /** @return array<int, string> */
    public static function eagerLoads(): array;
}
