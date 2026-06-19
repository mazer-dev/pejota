{{--
    Reusable searchable combobox with server-side search.

    @param string $field        Livewire property holding the selected id (e.g. 'newClient')
    @param string $searchProp   Livewire property holding the search term (e.g. 'clientSearch')
    @param array  $options      [id => label] already filtered server-side
    @param string|null $selectedLabel  Label of the current selection, if any
    @param string $placeholder  Text shown when nothing is selected
--}}
<div x-data="{ open: false }" class="relative" @click.outside="open = false">
    <button
        type="button"
        @click="open = ! open"
        class="fi-input flex w-full items-center justify-between gap-2 rounded-md border-gray-300 px-3 py-2 text-left text-sm dark:bg-white/5 dark:text-white"
    >
        <span class="truncate {{ $selectedLabel ? '' : 'text-gray-400' }}">{{ $selectedLabel ?: $placeholder }}</span>
        <x-filament::icon icon="heroicon-m-chevron-up-down" class="h-4 w-4 shrink-0 text-gray-400" />
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute z-50 mt-1 w-full rounded-md bg-white shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    >
        <input
            type="text"
            wire:model.live.debounce.300ms="{{ $searchProp }}"
            placeholder="{{ __('Search...') }}"
            class="fi-input block w-full rounded-t-md border-0 border-b border-gray-200 text-sm focus:ring-0 dark:border-white/10 dark:bg-white/5 dark:text-white"
            @click.stop
        />
        <ul class="max-h-48 overflow-y-auto py-1">
            @if ($selectedLabel)
                <li>
                    <button
                        type="button"
                        wire:click="$set('{{ $field }}', null)"
                        @click="open = false"
                        class="block w-full px-3 py-1.5 text-left text-xs text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5"
                    >
                        {{ __('Clear selection') }}
                    </button>
                </li>
            @endif
            @forelse ($options as $id => $label)
                <li wire:key="{{ $field }}-opt-{{ $id }}">
                    <button
                        type="button"
                        wire:click="$set('{{ $field }}', {{ $id }})"
                        @click="open = false"
                        class="block w-full truncate px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5"
                    >
                        {{ $label }}
                    </button>
                </li>
            @empty
                <li class="px-3 py-1.5 text-xs text-gray-400">{{ __('No results') }}</li>
            @endforelse
        </ul>
    </div>
</div>
