<?php

namespace Tests\Support;

use App\Contracts\SubscriptionResourceExtension;
use App\Models\Subscription;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class SubscriptionExtensionStub implements SubscriptionResourceExtension
{
    public static bool $active = true;

    public static bool $refuses = false;

    public static bool $throwsOnPersist = false;

    public static function reset(): void
    {
        self::$active = true;
        self::$refuses = false;
        self::$throwsOnPersist = false;
    }

    public static function isActive(): bool
    {
        return self::$active;
    }

    public static function stateKey(): string
    {
        return 'stub';
    }

    public static function tab(): Tab
    {
        return Tab::make('Stub')
            ->statePath(self::stateKey())
            ->schema([
                TextInput::make('note'),
            ]);
    }

    /** @return array<string, mixed> */
    public static function fillState(Subscription $record): array
    {
        return ['note' => $record->obs];
    }

    /** @param array<string, mixed> $data */
    public static function refuse(array $data, ?Subscription $record): void
    {
        if (self::$refuses) {
            throw new Halt;
        }
    }

    /** @param array<string, mixed> $state */
    public static function persist(Subscription $record, array $state): void
    {
        if (self::$throwsOnPersist) {
            throw new RuntimeException('stub persist failure');
        }

        $record->forceFill(['obs' => $state['note'] ?? null])->save();
    }

    /** @return array<int, TextColumn> */
    public static function columns(): array
    {
        return [
            TextColumn::make('obs')->label('Stub note'),
        ];
    }

    /** @return array<int, Filter> */
    public static function filters(): array
    {
        return [
            Filter::make('stub_noted')
                ->query(fn (Builder $query): Builder => $query->whereNotNull('obs')),
        ];
    }

    /** @return array<int, string> */
    public static function eagerLoads(): array
    {
        return ['vendor'];
    }
}
