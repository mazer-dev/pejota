<x-filament-panels::page>
    <form wire:submit="preview">
        {{ $this->form }}

        <div class="flex gap-2 mt-4">
            <x-filament::button type="submit">{{ __('Preview') }}</x-filament::button>
        </div>
        {{-- Export PDF / CSV are header actions (see getHeaderActions) so streamed downloads work reliably. --}}
    </form>

    @if ($hasPreview && $this->previewData)
        @php($preview = $this->previewData)
        <div class="mt-6 space-y-4">
            @forelse ($preview->groups as $group)
                <div class="rounded-lg border border-gray-200 dark:border-white/10 p-3">
                    <h3 class="font-semibold mb-2">{{ $group->label }}</h3>
                    <table class="w-full text-sm">
                        @foreach ($group->entries as $entry)
                            <tr>
                                <td>{{ $entry->date->format('Y-m-d') }}</td>
                                <td>{{ $entry->description }}</td>
                                <td class="text-right">{{ \App\Helpers\PejotaHelper::formatDuration($entry->minutes) }}</td>
                                @if ($preview->includeValue)
                                    <td class="text-right">{{ number_format($entry->value, 2) }}</td>
                                @endif
                            </tr>
                        @endforeach
                        <tr class="font-semibold border-t">
                            <td colspan="2">{{ __('Subtotal') }}</td>
                            <td class="text-right">{{ \App\Helpers\PejotaHelper::formatDuration($group->subtotalMinutes) }}</td>
                            @if ($preview->includeValue)
                                <td class="text-right">{{ number_format($group->subtotalValue, 2) }}</td>
                            @endif
                        </tr>
                    </table>
                </div>
            @empty
                <p class="text-gray-500">{{ __('No entries for this period.') }}</p>
            @endforelse

            <div class="font-bold text-right">
                {{ __('Total') }}: {{ \App\Helpers\PejotaHelper::formatDuration($preview->grandTotalMinutes) }}
                @if ($preview->includeValue) · {{ number_format($preview->grandTotalValue, 2) }} {{ $preview->currency }} @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
